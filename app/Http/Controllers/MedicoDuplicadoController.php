<?php

namespace App\Http\Controllers;

use App\Models\Medico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MedicoDuplicadoController extends Controller
{
    // ── Listar grupos de duplicados ──────────────────────────────

    public function index()
    {
        $grupos = $this->detectarDuplicados();
        return view('admin.medicos-duplicados', compact('grupos'));
    }

    // ── Fusionar un grupo ────────────────────────────────────────

    public function fusionar(Request $request)
    {
        $request->validate([
            'primario_id'  => 'required|exists:medicos,id',
            'duplicado_id' => 'required|exists:medicos,id|different:primario_id',
        ]);

        $primario  = Medico::findOrFail($request->primario_id);
        $duplicado = Medico::findOrFail($request->duplicado_id);

        DB::transaction(function () use ($primario, $duplicado) {
            $pId = $primario->id;
            $dId = $duplicado->id;

            // 1. turno_medicos
            DB::table('turno_medicos')->where('medico_id', $dId)->update(['medico_id' => $pId]);
            DB::table('turno_medicos')->where('medico_original_id', $dId)->update(['medico_original_id' => $pId]);
            DB::table('turno_medicos')->where('medico_reemplazo_id', $dId)->update(['medico_reemplazo_id' => $pId]);

            // 2. solicitudes_cambio_turno
            DB::table('solicitudes_cambio_turno')->where('medico_solicitante_id', $dId)->update(['medico_solicitante_id' => $pId]);
            DB::table('solicitudes_cambio_turno')->where('medico_receptor_id', $dId)->update(['medico_receptor_id' => $pId]);

            // 3. novedades
            DB::table('novedades')->where('medico_id', $dId)->update(['medico_id' => $pId]);

            // 4. ausencias
            DB::table('ausencias')->where('medico_id', $dId)->update(['medico_id' => $pId]);

            // 5. alertas_turno
            DB::table('alertas_turno')->where('medico_id', $dId)->update(['medico_id' => $pId]);

            // 6. secuencias_uci_detalle
            DB::table('secuencias_uci_detalle')->where('medico_id', $dId)->update(['medico_id' => $pId]);

            // 7. burnout_respuestas (sin UNIQUE aparte del PK)
            DB::table('burnout_respuestas')->where('medico_id', $dId)->update(['medico_id' => $pId]);

            // 8. burnout_alertas
            DB::table('burnout_alertas')->where('medico_id', $dId)->update(['medico_id' => $pId]);

            // 9. burnout_resultados — UNIQUE(medico_id, periodo_evaluado, encuesta_id)
            // MariaDB no soporta alias en DELETE, se resuelve en PHP
            $clavesBurnoutPrimario = DB::table('burnout_resultados')
                ->where('medico_id', $pId)
                ->get(['periodo_evaluado', 'encuesta_id'])
                ->map(fn($r) => $r->periodo_evaluado . '|' . $r->encuesta_id)
                ->toArray();
            if (!empty($clavesBurnoutPrimario)) {
                $idsConflictoBurnout = DB::table('burnout_resultados')
                    ->where('medico_id', $dId)
                    ->get(['id', 'periodo_evaluado', 'encuesta_id'])
                    ->filter(fn($r) => in_array($r->periodo_evaluado . '|' . $r->encuesta_id, $clavesBurnoutPrimario))
                    ->pluck('id')->toArray();
                if (!empty($idsConflictoBurnout)) {
                    DB::table('burnout_resultados')->whereIn('id', $idsConflictoBurnout)->delete();
                }
            }
            DB::table('burnout_resultados')->where('medico_id', $dId)->update(['medico_id' => $pId]);

            // 10. indicador_medicos — UNIQUE(medico_id, uci_id, mes, anio)
            $clavesIndicadorPrimario = DB::table('indicador_medicos')
                ->where('medico_id', $pId)
                ->get(['uci_id', 'mes', 'anio'])
                ->map(fn($r) => $r->uci_id . '|' . $r->mes . '|' . $r->anio)
                ->toArray();
            if (!empty($clavesIndicadorPrimario)) {
                $idsConflictoIndicador = DB::table('indicador_medicos')
                    ->where('medico_id', $dId)
                    ->get(['id', 'uci_id', 'mes', 'anio'])
                    ->filter(fn($r) => in_array($r->uci_id . '|' . $r->mes . '|' . $r->anio, $clavesIndicadorPrimario))
                    ->pluck('id')->toArray();
                if (!empty($idsConflictoIndicador)) {
                    DB::table('indicador_medicos')->whereIn('id', $idsConflictoIndicador)->delete();
                }
            }
            DB::table('indicador_medicos')->where('medico_id', $dId)->update(['medico_id' => $pId]);

            // 11. users — SET NULL automático, pero si duplicado tiene user y primario no, reasignamos
            $userDup = DB::table('users')->where('medico_id', $dId)->first();
            $userPri = DB::table('users')->where('medico_id', $pId)->first();
            if ($userDup && !$userPri) {
                DB::table('users')->where('medico_id', $dId)->update(['medico_id' => $pId]);
            } elseif ($userDup && $userPri) {
                DB::table('users')->where('medico_id', $dId)->update(['medico_id' => null]);
            }

            // 12. Normalizar nombre del primario a Proper Case
            $primario->update([
                'nombre'   => mb_convert_case(trim($primario->nombre),   MB_CASE_TITLE, 'UTF-8'),
                'apellido' => mb_convert_case(trim($primario->apellido ?? ''), MB_CASE_TITLE, 'UTF-8'),
            ]);

            // 13. Borrar el duplicado (ya no hay FKs apuntando a él)
            $duplicado->delete();
        });

        return back()->with('success',
            "Médico \"{$duplicado->nombre_completo}\" fusionado con \"{$primario->nombre_completo}\". Todos los turnos fueron preservados."
        );
    }

    // ── Fusionar TODOS los duplicados automáticamente ────────────

    public function fusionarTodos()
    {
        $grupos = $this->detectarDuplicados();
        $merged = 0;

        foreach ($grupos as $grupo) {
            // El primario es el que tiene más turnos (o el de menor ID si hay empate)
            $miembros = collect($grupo['medicos'])->sortByDesc('total_turnos');
            $primario  = $miembros->first();
            $duplicados = $miembros->skip(1);

            foreach ($duplicados as $dup) {
                $req = new Request([
                    'primario_id'  => $primario['id'],
                    'duplicado_id' => $dup['id'],
                ]);
                $req->validate([
                    'primario_id'  => 'required|exists:medicos,id',
                    'duplicado_id' => 'required|exists:medicos,id|different:primario_id',
                ]);
                $this->fusionar($req);
                $merged++;
            }
        }

        return back()->with('success', "Se fusionaron {$merged} registros duplicados.");
    }

    // ── Helper: detectar duplicados ──────────────────────────────

    private static function nombreFullKey(string $nombre, ?string $apellido): string
    {
        $full = trim($nombre);
        if ($apellido && trim($apellido) !== '') {
            $full .= ' ' . trim($apellido);
        }
        return strtolower(preg_replace('/\s+/', ' ', $full));
    }

    private function detectarDuplicados(): array
    {
        // Carga todos los médicos y agrupa en PHP para evitar SQL crudo complejo
        $todos = Medico::with('uci')->get();

        // Conteo de turnos y usuarios en una sola consulta para eficiencia
        $turnosPorMedico = DB::table('turno_medicos')
            ->select('medico_id', DB::raw('COUNT(*) as total'))
            ->groupBy('medico_id')
            ->pluck('total', 'medico_id');

        $usuariosPorMedico = DB::table('users')
            ->whereNotNull('medico_id')
            ->pluck('medico_id')
            ->flip();

        // Agrupar por nombre completo normalizado
        $porClave = [];
        foreach ($todos as $m) {
            $key = self::nombreFullKey($m->nombre, $m->apellido);
            $porClave[$key][] = [
                'id'              => $m->id,
                'nombre'          => $m->nombre,
                'apellido'        => $m->apellido,
                'nombre_completo' => $m->nombre_completo,
                'uci'             => $m->uci?->codigo ?? '—',
                'activo'          => $m->activo,
                'total_turnos'    => (int)($turnosPorMedico[$m->id] ?? 0),
                'tiene_user'      => isset($usuariosPorMedico[$m->id]),
            ];
        }

        $grupos = [];
        foreach ($porClave as $key => $medicos) {
            if (count($medicos) <= 1) continue;

            // Ordenar: primero el que tiene más turnos
            usort($medicos, fn($a, $b) => $b['total_turnos'] <=> $a['total_turnos']);

            $grupos[] = [
                'llave'   => $key,
                'medicos' => $medicos,
            ];
        }

        // Ordenar grupos por nombre
        usort($grupos, fn($a, $b) => strcmp($a['llave'], $b['llave']));

        return $grupos;
    }
}
