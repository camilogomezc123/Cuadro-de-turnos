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
            // Borrar registros del duplicado que colisionan con registros ya existentes del primario
            DB::table('burnout_resultados as br_dup')
                ->where('br_dup.medico_id', $dId)
                ->whereExists(fn($q) => $q->from('burnout_resultados as br_pri')
                    ->where('br_pri.medico_id', $pId)
                    ->whereColumn('br_pri.periodo_evaluado', 'br_dup.periodo_evaluado')
                    ->whereColumn('br_pri.encuesta_id', 'br_dup.encuesta_id')
                )
                ->delete();
            // Reasignar los que no colisionan
            DB::table('burnout_resultados')->where('medico_id', $dId)->update(['medico_id' => $pId]);

            // 10. indicador_medicos — UNIQUE(medico_id, uci_id, mes, anio)
            DB::table('indicador_medicos as im_dup')
                ->where('im_dup.medico_id', $dId)
                ->whereExists(fn($q) => $q->from('indicador_medicos as im_pri')
                    ->where('im_pri.medico_id', $pId)
                    ->whereColumn('im_pri.uci_id', 'im_dup.uci_id')
                    ->whereColumn('im_pri.mes', 'im_dup.mes')
                    ->whereColumn('im_pri.anio', 'im_dup.anio')
                )
                ->delete();
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

    private function detectarDuplicados(): array
    {
        $rawGrupos = DB::table('medicos')
            ->selectRaw("LOWER(TRIM(nombre)) as nom_key, LOWER(TRIM(IFNULL(apellido,''))) as ape_key, COUNT(*) as total")
            ->groupByRaw("LOWER(TRIM(nombre)), LOWER(TRIM(IFNULL(apellido,'')))")
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $grupos = [];
        foreach ($rawGrupos as $g) {
            $medicos = Medico::whereRaw('LOWER(TRIM(nombre)) = ?', [$g->nom_key])
                ->whereRaw("LOWER(TRIM(IFNULL(apellido,''))) = ?", [$g->ape_key])
                ->with('uci')
                ->get()
                ->map(fn($m) => [
                    'id'           => $m->id,
                    'nombre'       => $m->nombre,
                    'apellido'     => $m->apellido,
                    'nombre_completo' => $m->nombre_completo,
                    'uci'          => $m->uci?->codigo ?? '—',
                    'activo'       => $m->activo,
                    'total_turnos' => DB::table('turno_medicos')->where('medico_id', $m->id)->count(),
                    'tiene_user'   => DB::table('users')->where('medico_id', $m->id)->exists(),
                ]);

            $grupos[] = [
                'llave'   => $g->nom_key . ' ' . $g->ape_key,
                'medicos' => $medicos->sortByDesc('total_turnos')->values()->toArray(),
            ];
        }

        return $grupos;
    }
}
