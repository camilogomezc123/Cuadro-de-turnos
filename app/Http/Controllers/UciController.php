<?php

namespace App\Http\Controllers;

use App\Models\TurnoMedico;
use App\Models\Medico;
use App\Models\Uci;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UciController extends Controller
{
    private function mesAnio(Request $request): array
    {
        $now = now();
        return [
            (int) $request->get('mes',  $now->month),
            (int) $request->get('anio', $now->year),
        ];
    }

    private function rango(int $mes, int $anio): array
    {
        $inicio = Carbon::create($anio, $mes, 1)->startOfMonth()->toDateString();
        $fin    = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();
        return [$inicio, $fin];
    }

    public function index(Request $request)
    {
        [$mes, $anio] = $this->mesAnio($request);
        [$ini, $fin]  = $this->rango($mes, $anio);

        $ucis = Uci::where('activa', true)->orderBy('nombre')->get();

        // Cálculo de indicadores por UCI directo desde turno_medicos
        $indicadores = TurnoMedico::select(
                'uci_id',
                DB::raw('COUNT(DISTINCT medico_id) as total_medicos'),
                DB::raw('SUM(horas_total) as total_horas'),
                DB::raw('SUM(horas_nocturnas) as total_horas_nocturnas'),
                DB::raw('COUNT(*) as total_turnos'),
                DB::raw('SUM(CASE WHEN codigo_turno = "N" OR codigo_turno LIKE "%N%" THEN 1 ELSE 0 END) as turnos_nocturnos'),
            )
            ->whereBetween('fecha', [$ini, $fin])
            ->whereIn('codigo_turno', ['M','T','MT','N','MTN','MN'])
            ->groupBy('uci_id')
            ->get()
            ->keyBy('uci_id');

        // Cobertura: cuántos días del mes tuvo al menos un médico de turno
        $coberturaPorUci = TurnoMedico::select('uci_id', DB::raw('COUNT(DISTINCT fecha) as dias_cubiertos'))
            ->whereBetween('fecha', [$ini, $fin])
            ->whereIn('codigo_turno', ['M','T','MT','N','MTN','MN'])
            ->groupBy('uci_id')
            ->get()->keyBy('uci_id');

        $diasMes = Carbon::create($anio, $mes, 1)->daysInMonth;

        $nombresMeses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        return view('ucis.index', compact('ucis','indicadores','coberturaPorUci','mes','anio','diasMes','nombresMeses'));
    }

    public function show(Request $request, Uci $uci)
    {
        [$mes, $anio] = $this->mesAnio($request);
        [$ini, $fin]  = $this->rango($mes, $anio);

        // Indicador global de la UCI
        $indicador = TurnoMedico::select(
                DB::raw('COUNT(DISTINCT medico_id) as total_medicos'),
                DB::raw('SUM(horas_total) as total_horas'),
                DB::raw('SUM(horas_nocturnas) as total_horas_nocturnas'),
                DB::raw('COUNT(*) as total_turnos'),
            )
            ->where('uci_id', $uci->id)
            ->whereBetween('fecha', [$ini, $fin])
            ->whereIn('codigo_turno', ['M','T','MT','N','MTN','MN'])
            ->first();

        // Médicos con sus horas en esta UCI este mes
        $medicos = TurnoMedico::select(
                'medico_id',
                DB::raw('SUM(horas_total) as total_horas'),
                DB::raw('SUM(horas_nocturnas) as horas_nocturnas'),
                DB::raw('COUNT(*) as total_turnos'),
                DB::raw('SUM(CASE WHEN es_fin_semana = 1 THEN 1 ELSE 0 END) as fines_semana'),
                DB::raw('SUM(CASE WHEN codigo_turno IN ("N","MTN","MN") THEN 1 ELSE 0 END) as turnos_nocturnos'),
            )
            ->where('uci_id', $uci->id)
            ->whereBetween('fecha', [$ini, $fin])
            ->whereIn('codigo_turno', ['M','T','MT','N','MTN','MN'])
            ->groupBy('medico_id')
            ->with('medico')
            ->orderByDesc('total_horas')
            ->get();

        // Historial de meses con datos
        $historial = TurnoMedico::select(
                DB::raw('YEAR(fecha) as anio'),
                DB::raw('MONTH(fecha) as mes'),
                DB::raw('COUNT(DISTINCT medico_id) as medicos'),
                DB::raw('SUM(horas_total) as horas'),
            )
            ->where('uci_id', $uci->id)
            ->whereIn('codigo_turno', ['M','T','MT','N','MTN','MN'])
            ->groupBy(DB::raw('YEAR(fecha)'), DB::raw('MONTH(fecha)'))
            ->orderByDesc(DB::raw('YEAR(fecha)'))->orderByDesc(DB::raw('MONTH(fecha)'))
            ->limit(12)
            ->get();

        // Distribución por tipo de turno
        $distribucion = TurnoMedico::select('codigo_turno', DB::raw('COUNT(*) as cnt'))
            ->where('uci_id', $uci->id)
            ->whereBetween('fecha', [$ini, $fin])
            ->whereIn('codigo_turno', ['M','T','MT','N','MTN','MN'])
            ->groupBy('codigo_turno')
            ->get()->keyBy('codigo_turno');

        $nombresMeses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $diasMes = Carbon::create($anio, $mes, 1)->daysInMonth;

        return view('ucis.show', compact('uci','indicador','medicos','historial','distribucion','mes','anio','diasMes','nombresMeses'));
    }
}
