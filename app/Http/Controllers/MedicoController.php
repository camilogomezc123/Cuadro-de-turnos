<?php

namespace App\Http\Controllers;

use App\Models\Medico;
use App\Models\TurnoMedico;
use App\Models\Uci;
use App\Models\HorasAutorizacionExtra;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MedicoController extends Controller
{
    private function mesAnio(Request $request): array
    {
        return [
            (int) $request->get('mes',  now()->month),
            (int) $request->get('anio', now()->year),
        ];
    }

    public function index(Request $request)
    {
        [$mes, $anio] = $this->mesAnio($request);
        $uciId = $request->get('uci_id');
        $ucis  = Uci::where('activa', true)->orderBy('nombre')->get();

        $query = Medico::where('activo', true);
        if ($uciId) $query->where('uci_id', $uciId);
        $medicos = $query->with('uci')->orderBy('nombre')->get();

        // Calcular horas del mes para cada médico directamente desde turno_medicos
        $ini = Carbon::create($anio, $mes, 1)->startOfMonth()->toDateString();
        $fin = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();

        $horasPorMedico = TurnoMedico::select('medico_id',
                DB::raw('SUM(horas_total) as total_horas'),
                DB::raw('SUM(horas_nocturnas) as horas_nocturnas'),
                DB::raw('COUNT(*) as total_turnos'),
            )
            ->whereBetween('fecha', [$ini, $fin])
            ->whereIn('codigo_turno', ['M','T','MT','N','MTN','MN'])
            ->when($uciId, fn($q) => $q->where('uci_id', $uciId))
            ->groupBy('medico_id')
            ->get()->keyBy('medico_id');

        $nombresMeses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        return view('medicos.index', compact('medicos','ucis','uciId','mes','anio','horasPorMedico','nombresMeses'));
    }

    public function show(Request $request, Medico $medico)
    {
        [$mes, $anio] = $this->mesAnio($request);
        $ini = Carbon::create($anio, $mes, 1)->startOfMonth()->toDateString();
        $fin = Carbon::create($anio, $mes, 1)->endOfMonth()->toDateString();

        // Turnos del mes seleccionado (todas las UCIs del médico)
        $turnos = TurnoMedico::where('medico_id', $medico->id)
            ->whereBetween('fecha', [$ini, $fin])
            ->with('uci')
            ->orderBy('fecha')
            ->get();

        // Indicador calculado del mes
        $indicador = [
            'total_horas'      => $turnos->whereIn('codigo_turno',['M','T','MT','N','MTN','MN'])->sum('horas_total'),
            'horas_nocturnas'  => $turnos->sum('horas_nocturnas'),
            'total_turnos'     => $turnos->whereIn('codigo_turno',['M','T','MT','N','MTN','MN'])->count(),
            'fines_semana'     => $turnos->where('es_fin_semana', true)->whereIn('codigo_turno',['M','T','MT','N','MTN','MN'])->count(),
            'turnos_nocturnos' => $turnos->whereIn('codigo_turno',['N','MTN','MN'])->count(),
        ];
        $indicador['supera_200h']  = $indicador['total_horas'] > 200;
        $indicador['horas_diurnas']= $indicador['total_horas'] - $indicador['horas_nocturnas'];

        // Historial: horas por mes
        $historial = TurnoMedico::select(
                DB::raw('YEAR(fecha) as anio'),
                DB::raw('MONTH(fecha) as mes'),
                DB::raw('SUM(horas_total) as total_horas'),
                DB::raw('COUNT(*) as total_turnos'),
                'uci_id',
            )
            ->where('medico_id', $medico->id)
            ->whereIn('codigo_turno', ['M','T','MT','N','MTN','MN'])
            ->groupBy(DB::raw('YEAR(fecha)'), DB::raw('MONTH(fecha)'), 'uci_id')
            ->with('uci')
            ->orderByDesc(DB::raw('YEAR(fecha)'))->orderByDesc(DB::raw('MONTH(fecha)'))
            ->limit(24)
            ->get();

        // UCIs donde trabaja
        $ucisDelMedico = TurnoMedico::select('uci_id', DB::raw('SUM(horas_total) as horas'))
            ->where('medico_id', $medico->id)
            ->whereBetween('fecha', [$ini, $fin])
            ->whereIn('codigo_turno', ['M','T','MT','N','MTN','MN'])
            ->groupBy('uci_id')->with('uci')->get();

        $nombresMeses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        $autorizacionHoras = HorasAutorizacionExtra::where('medico_id', $medico->id)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->with('autorizadoPor')
            ->first();

        return view('medicos.show', compact(
            'medico','turnos','indicador','historial','ucisDelMedico',
            'mes','anio','nombresMeses','autorizacionHoras'
        ));
    }

    public function autorizarHoras(Request $request, Medico $medico)
    {
        $request->validate([
            'mes'  => 'required|integer|between:1,12',
            'anio' => 'required|integer|min:2020|max:2040',
        ]);

        HorasAutorizacionExtra::updateOrCreate(
            ['medico_id' => $medico->id, 'mes' => $request->mes, 'anio' => $request->anio],
            ['autorizado_por_user_id' => Auth::id()]
        );

        return back();
    }

    public function revocarHoras(Request $request, Medico $medico)
    {
        $mes  = (int)($request->mes  ?? now()->month);
        $anio = (int)($request->anio ?? now()->year);

        HorasAutorizacionExtra::where('medico_id', $medico->id)
            ->where('mes',  $mes)
            ->where('anio', $anio)
            ->delete();

        return back();
    }
}
