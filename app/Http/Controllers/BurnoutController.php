<?php

namespace App\Http\Controllers;

use App\Models\BurnoutEncuesta;
use App\Models\BurnoutPregunta;
use App\Models\BurnoutRespuesta;
use App\Models\BurnoutResultado;
use App\Models\BurnoutAlerta;
use App\Models\Medico;
use App\Models\TurnoMedico;
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
        $evaluados    = $resultados->count();
        $pctRespuesta = $totalMedicos > 0 ? round($evaluados / $totalMedicos * 100, 1) : 0;

        // Positivos/críticos usan los campos burnout_positivo/severo del modelo existente
        $positivos = $resultados->where('burnout_positivo', true)->count();
        $criticos  = $resultados->where('burnout_severo', true)->count();

        // Niveles de riesgo basados en clasificacion_agotamiento_emocional (reutilizamos el campo)
        $bajos     = $resultados->where('clasificacion_agotamiento_emocional', 'bajo')->count();
        $moderados = $resultados->where('clasificacion_agotamiento_emocional', 'moderado')->count();
        $altos     = $resultados->where('clasificacion_agotamiento_emocional', 'alto')->count();

        // Promedio por pregunta
        $promediosPregunta = [];
        if ($encuesta) {
            $resps = BurnoutRespuesta::where('encuesta_id', $encuesta->id)
                ->where('periodo_evaluado', $periodo)
                ->select('pregunta_id', DB::raw('AVG(respuesta_valor) as prom'), DB::raw('COUNT(*) as cnt'))
                ->groupBy('pregunta_id')->with('pregunta')
                ->get();
            foreach ($resps as $r) {
                $promediosPregunta[] = [
                    'texto' => $r->pregunta?->texto_pregunta ?? '—',
                    'dim'   => $r->pregunta?->dimension ?? '—',
                    'prom'  => round($r->prom, 2),
                ];
            }
        }

        // Comparación por UCI
        $porUci = $resultados->groupBy(fn($r) => $r->medico?->uci?->nombre ?? 'Sin UCI')
            ->map(fn($g) => [
                'evaluados' => $g->count(),
                'positivos' => $g->where('burnout_positivo', true)->count(),
                'criticos'  => $g->where('burnout_severo', true)->count(),
            ]);

        // Comparación >200h vs <=200h
        $mas200   = $resultados->where('supera_200h', true);
        $menos200 = $resultados->where('supera_200h', false);
        $comparHoras = [
            'mas200'   => ['cnt' => $mas200->count(),   'positivos' => $mas200->where('burnout_positivo',true)->count()],
            'menos200' => ['cnt' => $menos200->count(), 'positivos' => $menos200->where('burnout_positivo',true)->count()],
        ];

        // Comparación turnos nocturnos ≥4 vs <4
        $conNoct  = $resultados->where('turnos_nocturnos', '>=', 4);
        $sinNoct  = $resultados->where('turnos_nocturnos', '<', 4);
        $comparNocturno = [
            'con' => ['cnt' => $conNoct->count(), 'positivos' => $conNoct->where('burnout_positivo',true)->count()],
            'sin' => ['cnt' => $sinNoct->count(), 'positivos' => $sinNoct->where('burnout_positivo',true)->count()],
        ];

        // Tendencia mensual últimos 6 períodos
        $tendencia = BurnoutResultado::when($encuesta, fn($q) => $q->where('encuesta_id', $encuesta->id))
            ->select('periodo_evaluado',
                DB::raw('COUNT(*) as evaluados'),
                DB::raw('SUM(burnout_positivo) as positivos'),
                DB::raw('SUM(burnout_severo) as criticos'),
            )
            ->groupBy('periodo_evaluado')
            ->orderByDesc('periodo_evaluado')
            ->limit(6)->get()->reverse()->values();

        $alertas  = BurnoutAlerta::with('medico')->where('estado','activa')
            ->where('periodo_evaluado', $periodo)->orderByDesc('nivel_riesgo')->get();

        $periodos = BurnoutResultado::when($encuesta, fn($q) => $q->where('encuesta_id', $encuesta->id))
            ->distinct()->orderByDesc('periodo_evaluado')->pluck('periodo_evaluado');

        return view('burnout.admin.index', compact(
            'encuesta','periodo','periodos','resultados','totalMedicos',
            'evaluados','pctRespuesta','positivos','criticos',
            'bajos','moderados','altos',
            'promediosPregunta','porUci','comparHoras','comparNocturno','tendencia','alertas'
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
        if (!$encuesta->activa) $encuesta->update(['activa' => true]);
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
                'nombre'           => $request->nombre,
                'periodo'          => $request->periodo,
                'permite_posponer' => $request->boolean('permite_posponer'),
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

        $resultados = BurnoutResultado::with(['medico.uci'])
            ->where('periodo_evaluado', $periodo)
            ->when($encuesta, fn($q) => $q->where('encuesta_id', $encuesta->id))
            ->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Burnout ' . $periodo);

        $headers = ['Médico','UCI','Período','Nivel Riesgo','Tamizaje Positivo',
                    'Alerta Crítica','AE','DP','RP','Horas Mes','T. Nocturnos','>200h'];
        foreach ($headers as $col => $h) {
            $sheet->setCellValue([$col+1, 1], $h);
        }
        $sheet->getStyle([1,1,count($headers),1])->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF1565C0']],
        ]);

        $row = 2;
        foreach ($resultados as $r) {
            $nivel = $r->burnout_severo ? 'Alto' : ($r->burnout_positivo ? 'Moderado' : 'Bajo');
            $sheet->fromArray([
                $r->medico?->nombre_completo ?? '—',
                $r->medico?->uci?->nombre ?? '—',
                $r->periodo_evaluado,
                $nivel,
                $r->burnout_positivo ? 'Sí' : 'No',
                $r->burnout_severo   ? 'Sí' : 'No',
                $r->puntaje_agotamiento_emocional,
                $r->puntaje_despersonalizacion,
                $r->puntaje_realizacion_personal,
                $r->horas_programadas_mes,
                $r->turnos_nocturnos,
                $r->supera_200h ? 'Sí' : 'No',
            ], null, null, $row++);
        }
        foreach (range(1, count($headers)) as $c) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        return response()->streamDownload(fn() => $writer->save('php://output'),
            "Burnout_Tamizaje_{$periodo}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }

    // ── API: verificar si médico debe responder ──────────────────

    public function verificar(Request $request)
    {
        $medico = Auth::user()->medico;
        if (!$medico) return response()->json(['mostrar' => false]);

        $encuesta = BurnoutEncuesta::activa();
        if (!$encuesta) return response()->json(['mostrar' => false]);

        $periodo = $encuesta->periodoActual();
        if (BurnoutRespuesta::where('medico_id', $medico->id)
            ->where('encuesta_id', $encuesta->id)
            ->where('periodo_evaluado', $periodo)->exists()) {
            return response()->json(['mostrar' => false]);
        }

        if ($encuesta->permite_posponer && session("burnout_pospuesto_{$encuesta->id}_{$periodo}")) {
            return response()->json(['mostrar' => false]);
        }

        $preguntas = $encuesta->preguntas()->where('activa', true)->orderBy('orden')->get();

        return response()->json([
            'mostrar'          => true,
            'encuesta_id'      => $encuesta->id,
            'periodo'          => $periodo,
            'permite_posponer' => $encuesta->permite_posponer,
            'preguntas'        => $preguntas->map(fn($p) => [
                'id'         => $p->id,
                'texto'      => $p->texto_pregunta,
                'dimension'  => $p->dimension,
                'orden'      => $p->orden,
                'obligatoria'=> $p->obligatoria,
            ]),
        ]);
    }

    // ── Guardar respuestas ───────────────────────────────────────

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

        if (BurnoutRespuesta::where('medico_id', $medico->id)
            ->where('encuesta_id', $encuesta->id)
            ->where('periodo_evaluado', $periodo)->exists()) {
            return response()->json(['ok' => false, 'mensaje' => 'Ya respondió esta encuesta.']);
        }

        DB::transaction(function () use ($request, $medico, $encuesta, $periodo) {
            $preguntas = $encuesta->preguntas()->where('activa', true)->get()->keyBy('id');

            $pAE = $pDP = $pRP = $pCarga = $pImpacto = 0;

            foreach ($request->respuestas as $r) {
                BurnoutRespuesta::create([
                    'encuesta_id'     => $encuesta->id,
                    'medico_id'       => $medico->id,
                    'pregunta_id'     => $r['pregunta_id'],
                    'respuesta_valor' => (int)$r['valor'],
                    'periodo_evaluado'=> $periodo,
                ]);
                $preg = $preguntas[$r['pregunta_id']] ?? null;
                if (!$preg) continue;
                match ($preg->dimension) {
                    'agotamiento_emocional'      => $pAE     = (int)$r['valor'],
                    'carga_laboral_recuperacion' => $pCarga  = (int)$r['valor'],
                    'despersonalizacion'         => $pDP     = (int)$r['valor'],
                    'realizacion_personal'       => $pRP     = (int)$r['valor'],
                    'impacto_turnos'             => $pImpacto= (int)$r['valor'],
                    default                      => null,
                };
            }

            // Nivel de riesgo global
            $maxVal = max($pAE, $pCarga, $pDP, $pRP, $pImpacto);
            $nivel  = match (true) {
                $maxVal >= 5 => 'alto',
                $maxVal >= 3 => 'moderado',
                default      => 'bajo',
            };

            // Tamizaje positivo
            $tamizajePositivo = ($pAE >= 4 || $pDP >= 4);

            // Cruce turnos
            [$year, $month] = explode('-', $periodo);
            $ini = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
            $fin = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

            $turnosMes  = TurnoMedico::where('medico_id', $medico->id)->whereBetween('fecha', [$ini, $fin])->get();
            $horasMes   = $turnosMes->whereIn('codigo_turno',['M','T','MT','N','MTN','MN'])->sum('horas_total');
            $nocturnos  = $turnosMes->whereIn('codigo_turno',['N','MTN','MN'])->count();
            $supera200  = $horasMes > 200;
            $diaLargo   = $turnosMes->whereIn('codigo_turno',['MTN','MN'])->where('es_fin_semana', false)->count() > 0;

            // Alerta crítica
            $alertaCritica = $pAE >= 5 || $pDP >= 5
                || ($tamizajePositivo && $supera200)
                || ($tamizajePositivo && $nocturnos >= 4)
                || ($tamizajePositivo && $diaLargo);

            // clasificacion_realizacion_personal usa ENUM('alta','moderada','baja') — femenino
            $clRP = match ($nivel) {
                'alto'     => 'alta',
                'moderado' => 'moderada',
                default    => 'baja',
            };

            $resultado = BurnoutResultado::updateOrCreate(
                ['medico_id' => $medico->id, 'encuesta_id' => $encuesta->id, 'periodo_evaluado' => $periodo],
                [
                    'puntaje_agotamiento_emocional'       => $pAE,
                    'clasificacion_agotamiento_emocional' => $nivel,
                    'puntaje_despersonalizacion'          => $pDP,
                    'clasificacion_despersonalizacion'    => $nivel,
                    'puntaje_realizacion_personal'        => $pRP,
                    'clasificacion_realizacion_personal'  => $clRP,
                    'burnout_positivo'         => $tamizajePositivo,
                    'burnout_severo'           => $alertaCritica,
                    'horas_programadas_mes'    => $horasMes,
                    'turnos_nocturnos'         => $nocturnos,
                    'fines_semana_trabajados'  => $turnosMes->where('es_fin_semana', true)->count(),
                    'supera_200h'              => $supera200,
                ]
            );

            if ($alertaCritica) {
                BurnoutAlerta::updateOrCreate(
                    ['resultado_id' => $resultado->id, 'tipo_alerta' => 'alerta_critica'],
                    ['medico_id' => $medico->id, 'periodo_evaluado' => $periodo,
                     'descripcion' => "Dr. {$medico->nombre_completo}: alerta crítica en tamizaje {$periodo}.",
                     'nivel_riesgo' => 'critico', 'estado' => 'activa']
                );
            } elseif ($tamizajePositivo) {
                BurnoutAlerta::updateOrCreate(
                    ['resultado_id' => $resultado->id, 'tipo_alerta' => 'tamizaje_positivo'],
                    ['medico_id' => $medico->id, 'periodo_evaluado' => $periodo,
                     'descripcion' => "Dr. {$medico->nombre_completo}: tamizaje positivo en {$periodo}.",
                     'nivel_riesgo' => 'alto', 'estado' => 'activa']
                );
            }
        });

        $resultado = BurnoutResultado::where('medico_id', $medico->id)
            ->where('encuesta_id', $request->encuesta_id)
            ->where('periodo_evaluado', $periodo)->first();

        $nivel   = $resultado->burnout_severo ? 'critico' : ($resultado->burnout_positivo ? 'positivo' : 'normal');
        $mensaje = match ($nivel) {
            'critico'  => 'Tus respuestas muestran señales importantes de desgaste profesional. Te recomendamos buscar apoyo institucional o profesional. Esta información es confidencial.',
            'positivo' => 'Tus respuestas sugieren señales de desgaste profesional. No reemplaza valoración clínica. Recomendamos revisar carga laboral y usar los canales institucionales de bienestar.',
            default    => 'Tus respuestas no muestran señales de alerta en esta medición. Recuerda mantener pausas, descanso y autocuidado.',
        };

        return response()->json(['ok' => true, 'mensaje' => $mensaje, 'nivel' => $nivel]);
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
