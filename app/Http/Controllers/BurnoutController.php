<?php

namespace App\Http\Controllers;

use App\Models\BurnoutEncuesta;
use App\Models\BurnoutPregunta;
use App\Models\BurnoutRespuesta;
use App\Models\BurnoutResultado;
use App\Models\BurnoutAlerta;
use App\Models\Medico;
use App\Models\TurnoMedico;
use App\Models\Uci;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BurnoutController extends Controller
{
    // ── Panel administrador ──────────────────────────────────────

    public function index(Request $request)
    {
        $encuesta = BurnoutEncuesta::activa();
        $periodo  = $request->get('periodo', $encuesta?->periodoActual() ?? now()->format('Y-m'));

        $resultados = BurnoutResultado::with(['medico.uci'])
            ->where('periodo_evaluado', $periodo)
            ->when($encuesta, fn($q) => $q->where('encuesta_id', $encuesta->id))
            ->get();

        $totalMedicos = Medico::where('activo', true)->count();

        $agregado = [
            'evaluados'          => $resultados->count(),
            'pct_respondio'      => $totalMedicos > 0 ? round($resultados->count() / $totalMedicos * 100, 1) : 0,
            'prom_ae'            => round($resultados->avg('puntaje_agotamiento_emocional') ?? 0, 1),
            'prom_dp'            => round($resultados->avg('puntaje_despersonalizacion') ?? 0, 1),
            'prom_rp'            => round($resultados->avg('puntaje_realizacion_personal') ?? 0, 1),
            'burnout_positivos'  => $resultados->where('burnout_positivo', true)->count(),
            'burnout_severos'    => $resultados->where('burnout_severo', true)->count(),
            'pct_positivo'       => $resultados->count() > 0
                ? round($resultados->where('burnout_positivo', true)->count() / $resultados->count() * 100, 1)
                : 0,
            'pct_severo'         => $resultados->count() > 0
                ? round($resultados->where('burnout_severo', true)->count() / $resultados->count() * 100, 1)
                : 0,
        ];

        // Distribución por UCI
        $porUci = $resultados->groupBy('medico.uci.nombre')->map(fn($g) => [
            'total'           => $g->count(),
            'burnout_positivo'=> $g->where('burnout_positivo', true)->count(),
            'burnout_severo'  => $g->where('burnout_severo', true)->count(),
        ]);

        // Periodos disponibles
        $periodos = BurnoutResultado::when($encuesta, fn($q) => $q->where('encuesta_id', $encuesta->id))
            ->distinct()->orderByDesc('periodo_evaluado')->pluck('periodo_evaluado');

        $alertas = BurnoutAlerta::with('medico')->where('estado','activa')
            ->when($periodo, fn($q) => $q->where('periodo_evaluado', $periodo))
            ->orderByDesc('nivel_riesgo')->get();

        $ucis = Uci::orderBy('nombre')->get();

        return view('burnout.admin.index', compact(
            'encuesta','periodo','periodos','resultados','agregado','porUci','alertas','totalMedicos','ucis'
        ));
    }

    // ── Gestión de preguntas ─────────────────────────────────────

    public function preguntas()
    {
        $encuesta  = BurnoutEncuesta::with('preguntas')->activa();
        $encuestas = BurnoutEncuesta::orderByDesc('id')->get();
        return view('burnout.admin.preguntas', compact('encuesta','encuestas'));
    }

    public function actualizarPregunta(Request $request, BurnoutPregunta $pregunta)
    {
        $request->validate(['texto_pregunta' => 'required|string|max:500']);
        $pregunta->update(['texto_pregunta' => $request->texto_pregunta]);
        return back()->with('success', 'Pregunta actualizada.');
    }

    public function toggleEncuesta(Request $request, BurnoutEncuesta $encuesta)
    {
        BurnoutEncuesta::where('activa', true)->update(['activa' => false]);
        if (!$encuesta->activa) {
            $encuesta->update(['activa' => true]);
        }
        return back()->with('success', 'Estado de la encuesta actualizado.');
    }

    public function configurar(Request $request)
    {
        $request->validate([
            'nombre'           => 'required|string|max:150',
            'periodo'          => 'required|in:mensual,bimestral,trimestral',
            'permite_posponer' => 'boolean',
        ]);

        $encuesta = BurnoutEncuesta::activa();
        if ($encuesta) {
            $encuesta->update([
                'periodo'          => $request->periodo,
                'permite_posponer' => $request->boolean('permite_posponer'),
                'nombre'           => $request->nombre,
            ]);
        } else {
            $encuesta = BurnoutEncuesta::create([
                'nombre'           => $request->nombre,
                'periodo'          => $request->periodo,
                'activa'           => true,
                'permite_posponer' => $request->boolean('permite_posponer'),
                'creada_por'       => Auth::id(),
            ]);
        }
        return back()->with('success', 'Configuración guardada.');
    }

    public function atenderAlerta(BurnoutAlerta $alerta)
    {
        $alerta->update(['estado' => 'atendida']);
        return back()->with('success', 'Alerta marcada como atendida.');
    }

    // ── Exportar Excel ───────────────────────────────────────────

    public function exportarExcel(Request $request)
    {
        $encuesta = BurnoutEncuesta::activa();
        $periodo  = $request->get('periodo', $encuesta?->periodoActual() ?? now()->format('Y-m'));

        $resultados = BurnoutResultado::with(['medico.uci','alertas'])
            ->where('periodo_evaluado', $periodo)
            ->when($encuesta, fn($q) => $q->where('encuesta_id', $encuesta->id))
            ->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Burnout ' . $periodo);

        $headers = [
            'Médico','UCI','Período','Horas Prog.','Turnos Noct.',
            'Fines Sem.','Puntaje AE','Clasificación AE',
            'Puntaje DP','Clasificación DP','Puntaje RP','Clasificación RP',
            'Burnout Positivo','Burnout Severo','Alertas',
        ];

        foreach ($headers as $col => $h) {
            $sheet->setCellValue([$col + 1, 1], $h);
        }
        $sheet->getStyle([1, 1, count($headers), 1])->applyFromArray([
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF1565C0']],
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        ]);

        $row = 2;
        foreach ($resultados as $r) {
            $alertasTxt = $r->alertas->pluck('tipo_alerta')->join(', ');
            $sheet->fromArray([
                $r->medico?->nombre_completo ?? '—',
                $r->medico?->uci?->nombre ?? '—',
                $r->periodo_evaluado,
                $r->horas_programadas_mes,
                $r->turnos_nocturnos,
                $r->fines_semana_trabajados,
                $r->puntaje_agotamiento_emocional,
                ucfirst($r->clasificacion_agotamiento_emocional),
                $r->puntaje_despersonalizacion,
                ucfirst($r->clasificacion_despersonalizacion),
                $r->puntaje_realizacion_personal,
                ucfirst($r->clasificacion_realizacion_personal),
                $r->burnout_positivo ? 'Sí' : 'No',
                $r->burnout_severo   ? 'Sí' : 'No',
                $alertasTxt,
            ], null, null, $row++);
        }

        foreach (range(1, count($headers)) as $c) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = "Burnout_Reporte_{$periodo}.xlsx";

        return response()->streamDownload(fn() => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ── API: verificar si médico debe responder encuesta ─────────

    public function verificar(Request $request)
    {
        $medico   = Auth::user()->medico;
        if (!$medico) return response()->json(['mostrar' => false]);

        $encuesta = BurnoutEncuesta::activa();
        if (!$encuesta) return response()->json(['mostrar' => false]);

        $periodo  = $encuesta->periodoActual();
        $yaRespondio = BurnoutRespuesta::where('medico_id', $medico->id)
            ->where('encuesta_id', $encuesta->id)
            ->where('periodo_evaluado', $periodo)->exists();

        if ($yaRespondio) return response()->json(['mostrar' => false]);

        // Verificar si pospuso hoy
        $pospusoHoy = session("burnout_pospuesto_{$encuesta->id}_{$periodo}");
        if ($pospusoHoy && $encuesta->permite_posponer) {
            return response()->json(['mostrar' => false]);
        }

        $preguntas = $encuesta->preguntas()->where('activa', true)->get();

        return response()->json([
            'mostrar'          => true,
            'encuesta_id'      => $encuesta->id,
            'periodo'          => $periodo,
            'permite_posponer' => $encuesta->permite_posponer,
            'preguntas'        => $preguntas->map(fn($p) => [
                'id'        => $p->id,
                'texto'     => $p->texto_pregunta,
                'dimension' => $p->dimension,
                'orden'     => $p->orden,
                'obligatoria'=> $p->obligatoria,
            ]),
        ]);
    }

    // ── Guardar respuestas del médico ────────────────────────────

    public function responder(Request $request)
    {
        $request->validate([
            'encuesta_id' => 'required|exists:burnout_encuestas,id',
            'periodo'     => 'required|string',
            'respuestas'  => 'required|array',
            'respuestas.*.pregunta_id' => 'required|exists:burnout_preguntas,id',
            'respuestas.*.valor'       => 'required|integer|between:0,6',
        ]);

        $medico   = Auth::user()->medico;
        $encuesta = BurnoutEncuesta::findOrFail($request->encuesta_id);
        $periodo  = $request->periodo;

        if (!$medico) return response()->json(['ok' => false, 'mensaje' => 'Sin perfil médico.']);

        // Evitar doble respuesta
        if (BurnoutRespuesta::where('medico_id', $medico->id)
            ->where('encuesta_id', $encuesta->id)
            ->where('periodo_evaluado', $periodo)->exists()) {
            return response()->json(['ok' => false, 'mensaje' => 'Ya respondió esta encuesta.']);
        }

        DB::transaction(function () use ($request, $medico, $encuesta, $periodo) {
            // Guardar respuestas individuales
            foreach ($request->respuestas as $r) {
                BurnoutRespuesta::create([
                    'encuesta_id'    => $encuesta->id,
                    'medico_id'      => $medico->id,
                    'pregunta_id'    => $r['pregunta_id'],
                    'respuesta_valor'=> (int)$r['valor'],
                    'periodo_evaluado'=> $periodo,
                ]);
            }

            // Calcular puntajes por dimensión
            $preguntas = $encuesta->preguntas()->where('activa', true)->get()->keyBy('id');
            $pAE = $pDP = $pRP = 0;

            foreach ($request->respuestas as $r) {
                $preg = $preguntas[$r['pregunta_id']] ?? null;
                if (!$preg) continue;
                match ($preg->dimension) {
                    'agotamiento_emocional' => $pAE += (int)$r['valor'],
                    'despersonalizacion'    => $pDP += (int)$r['valor'],
                    'realizacion_personal'  => $pRP += (int)$r['valor'],
                };
            }

            // Clasificar
            $clAE = $pAE <= 18 ? 'bajo' : ($pAE <= 26 ? 'moderado' : 'alto');
            $clDP = $pDP <= 5  ? 'bajo' : ($pDP <= 9  ? 'moderado' : 'alto');
            $clRP = $pRP >= 40 ? 'alta' : ($pRP >= 34 ? 'moderada' : 'baja');

            $bPositivo = ($pAE >= 27 || $pDP >= 10);
            $bSevero   = ($pAE >= 27 && $pDP >= 10 && $pRP <= 33);

            // Cruce con cuadro de turnos del mes actual
            [$year, $month] = explode('-', $periodo);
            $fechaIni = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
            $fechaFin = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

            $turnosMes = TurnoMedico::where('medico_id', $medico->id)
                ->whereBetween('fecha', [$fechaIni, $fechaFin])->get();

            $horasMes    = $turnosMes->sum('horas_total');
            $nocturnos   = $turnosMes->whereIn('codigo_turno', ['N','MTN','MN'])->count();
            $finSemanaMes= $turnosMes->where('es_fin_semana', true)
                ->where('codigo_turno', '!=', '')->groupBy('fecha')->count();

            $resultado = BurnoutResultado::updateOrCreate(
                ['medico_id' => $medico->id, 'encuesta_id' => $encuesta->id, 'periodo_evaluado' => $periodo],
                [
                    'puntaje_agotamiento_emocional'       => $pAE,
                    'clasificacion_agotamiento_emocional' => $clAE,
                    'puntaje_despersonalizacion'           => $pDP,
                    'clasificacion_despersonalizacion'     => $clDP,
                    'puntaje_realizacion_personal'         => $pRP,
                    'clasificacion_realizacion_personal'   => $clRP,
                    'burnout_positivo'    => $bPositivo,
                    'burnout_severo'      => $bSevero,
                    'horas_programadas_mes'      => $horasMes,
                    'turnos_nocturnos'           => $nocturnos,
                    'fines_semana_trabajados'    => $finSemanaMes,
                    'supera_200h'                => ($horasMes > 200),
                ]
            );

            // Generar alertas
            if ($bSevero) {
                BurnoutAlerta::updateOrCreate(
                    ['resultado_id' => $resultado->id, 'tipo_alerta' => 'burnout_severo'],
                    ['medico_id' => $medico->id, 'periodo_evaluado' => $periodo,
                     'descripcion' => "Dr. {$medico->nombre_completo}: burnout severo en {$periodo}.",
                     'nivel_riesgo' => 'critico', 'estado' => 'activa']
                );
            }
            if ($bPositivo && $horasMes > 200) {
                BurnoutAlerta::updateOrCreate(
                    ['resultado_id' => $resultado->id, 'tipo_alerta' => 'burnout_positivo_exceso_horas'],
                    ['medico_id' => $medico->id, 'periodo_evaluado' => $periodo,
                     'descripcion' => "Dr. {$medico->nombre_completo}: burnout positivo + {$horasMes}h (>200h).",
                     'nivel_riesgo' => 'alto', 'estado' => 'activa']
                );
            }
            if ($bPositivo && $nocturnos >= 8) {
                BurnoutAlerta::updateOrCreate(
                    ['resultado_id' => $resultado->id, 'tipo_alerta' => 'burnout_positivo_nocturnos'],
                    ['medico_id' => $medico->id, 'periodo_evaluado' => $periodo,
                     'descripcion' => "Dr. {$medico->nombre_completo}: burnout positivo + {$nocturnos} turnos nocturnos.",
                     'nivel_riesgo' => 'alto', 'estado' => 'activa']
                );
            }
        });

        // Resultado para mostrar al médico
        $resultado = BurnoutResultado::where('medico_id', $medico->id)
            ->where('encuesta_id', $request->encuesta_id)
            ->where('periodo_evaluado', $periodo)->first();

        $mensaje = match (true) {
            $resultado->burnout_severo   => 'Tus respuestas muestran señales importantes de desgaste profesional. Te recomendamos buscar apoyo institucional o profesional. Esta información será tratada con confidencialidad y orientada a bienestar.',
            $resultado->burnout_positivo => 'Tus respuestas sugieren señales de desgaste profesional. Esta encuesta no reemplaza una valoración clínica, pero recomendamos revisar carga laboral, descanso, apoyo emocional y canales institucionales de bienestar.',
            default                      => 'Tus respuestas no sugieren criterios altos de burnout en esta medición. Recuerda mantener pausas, descanso y autocuidado.',
        };

        return response()->json([
            'ok'      => true,
            'mensaje' => $mensaje,
            'nivel'   => $resultado->burnout_severo ? 'severo' : ($resultado->burnout_positivo ? 'positivo' : 'normal'),
        ]);
    }

    public function posponer(Request $request)
    {
        $encuesta = BurnoutEncuesta::activa();
        if ($encuesta?->permite_posponer) {
            session(["burnout_pospuesto_{$encuesta->id}_{$encuesta->periodoActual()}" => true]);
        }
        return response()->json(['ok' => true]);
    }
}
