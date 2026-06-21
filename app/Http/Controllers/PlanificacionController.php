<?php

namespace App\Http\Controllers;

use App\Models\ArchivoCargado;
use App\Models\Medico;
use App\Models\TurnoMedico;
use App\Models\Uci;
use App\Models\TipoTurno;
use App\Services\TurnoService;
use App\Services\AlertService;
use Illuminate\Http\Request;

class PlanificacionController extends Controller
{
    public function __construct(
        private TurnoService $turnoService,
        private AlertService $alertService,
    ) {}

    public function index(Request $request)
    {
        $archivos  = ArchivoCargado::orderByDesc('anio')->orderByDesc('mes')->get();
        $ucis      = Uci::where('activa', true)->orderBy('nombre')->get();
        $tiposTurno= TipoTurno::where('activo', true)->orderBy('codigo')->get();

        $archivoId = $request->integer('archivo_id', $archivos->first()?->id ?? 0);
        $uciId     = $request->integer('uci_id', 0);
        $medicoId  = $request->integer('medico_id', 0);
        $soloAlertas = $request->boolean('solo_alertas', false);

        $archivo    = $archivoId ? ArchivoCargado::find($archivoId) : null;
        $grilla     = [];
        $medicos    = collect();
        $diasEnMes  = 31;

        if ($archivo) {
            $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $archivo->mes, $archivo->anio);
            $query = TurnoMedico::with(['medico.uci', 'uci'])
                ->where('archivo_id', $archivoId);

            if ($uciId)     $query->where('uci_id', $uciId);
            if ($medicoId)  $query->where('medico_id', $medicoId);
            if ($soloAlertas) $query->where('tiene_alerta', true);

            $turnos = $query->orderBy('medico_id')->orderBy('dia_numero')->get();

            // Agrupar: [medico_id => [dia => turno]]
            foreach ($turnos as $t) {
                $grilla[$t->medico_id][$t->dia_numero] = $t;
                $medicos[$t->medico_id] = $t->medico;
            }
        }

        // Días del mes con su letra de día de semana
        $diasInfo = [];
        if ($archivo) {
            for ($d = 1; $d <= $diasEnMes; $d++) {
                $fecha    = \Carbon\Carbon::create($archivo->anio, $archivo->mes, $d);
                $dow      = $fecha->dayOfWeek; // 0=Dom
                $letras   = ['D','L','M','M','J','V','S'];
                $diasInfo[$d] = [
                    'fecha'     => $fecha->toDateString(),
                    'letra'     => $letras[$dow],
                    'es_finde'  => in_array($dow, [0, 6]),
                    'es_domingo'=> $dow === 0,
                ];
            }
        }

        return view('planificacion.index', compact(
            'archivos', 'archivo', 'ucis', 'tiposTurno',
            'grilla', 'medicos', 'diasEnMes', 'diasInfo',
            'archivoId', 'uciId', 'medicoId', 'soloAlertas'
        ));
    }

    /** AJAX: editar una celda de turno */
    public function editarCelda(Request $request)
    {
        $request->validate([
            'turno_id'     => 'required|integer|exists:turno_medicos,id',
            'codigo_turno' => 'required|string|max:10',
        ]);

        try {
            $resultado = $this->turnoService->editarTurno(
                $request->integer('turno_id'),
                strtoupper(trim($request->string('codigo_turno'))),
                $request->string('usuario', 'coordinador')
            );

            // Ejecutar alertas para el turno editado
            $turno = $resultado['turno'];
            $this->alertService->validarTurnoEditado($turno);

            return response()->json([
                'ok'              => true,
                'codigo'          => $resultado['codigo'],
                'horas_total'     => $resultado['horas_total'],
                'horas_diurnas'   => $resultado['horas_diurnas'],
                'horas_nocturnas' => $resultado['horas_nocturnas'],
                'tiene_alerta'    => $turno->fresh()->tiene_alerta,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    /** AJAX: resumen de horas de un médico en el mes */
    public function resumenMedico(Request $request)
    {
        $archivoId = $request->integer('archivo_id');
        $medicoId  = $request->integer('medico_id');

        $indicador = \App\Models\IndicadorMedico::where('archivo_id', $archivoId)
            ->where('medico_id', $medicoId)
            ->first();

        return response()->json($indicador ?? []);
    }
}
