<?php

namespace App\Http\Controllers;

use App\Models\ArchivoCargado;
use App\Models\AlertaTurno;
use App\Models\TurnoMedico;
use App\Models\Medico;
use App\Models\Novedad;
use App\Models\SolicitudCambioTurno;
use App\Services\HoraConsolidadoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private HoraConsolidadoService $horaService) {}

    public function index(Request $request)
    {
        $mes    = (int)($request->mes  ?? now()->month);
        $anio   = (int)($request->anio ?? now()->year);
        $meses  = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                   'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        $archivos  = ArchivoCargado::where('procesado', true)->orderByDesc('anio')->orderByDesc('mes')->get();
        $archivoId = $request->archivo_id ?? ArchivoCargado::where('procesado',true)
                        ->where('mes',$mes)->where('anio',$anio)->value('id');
        $archivo   = $archivos->find($archivoId);

        // ── KPIs principales ────────────────────────────────────

        $totalMedicos = Medico::where('activo', true)->count();

        // Horas multi-UCI del mes
        $horasProgramadas = TurnoMedico::whereYear('fecha',$anio)->whereMonth('fecha',$mes)
            ->sum('horas_total');

        $horasReconocidas = TurnoMedico::whereYear('fecha',$anio)->whereMonth('fecha',$mes)
            ->where('fue_laborado', true)
            ->sum(DB::raw('COALESCE(horas_reconocidas, horas_total)'));

        $horasRestadas = $horasProgramadas - $horasReconocidas;

        // Médicos con más de 200h
        $medicosExceso200 = $this->medicosConExceso200($mes, $anio);

        // Alertas abiertas
        $alertasAbiertas  = AlertaTurno::where('estado','abierta')->count();
        $alertas12HHabil  = AlertaTurno::where('estado','abierta')->where('tipo','EXCESO_12H_HABIL')
                                ->whereYear('created_at',$anio)->whereMonth('created_at',$mes)->count();

        // Turnos y solicitudes
        $turnosOfrecidos       = TurnoMedico::where('estado_turno','ofrecido')->count();
        $cambiosPendientes     = SolicitudCambioTurno::pendientesParaMaestro()->count();
        $turnosDescubiertos    = TurnoMedico::where('estado_turno','descubierto')
                                    ->whereYear('fecha',$anio)->whereMonth('fecha',$mes)->count();
        $novedadesMes          = Novedad::whereYear('fecha',$anio)->whereMonth('fecha',$mes)->count();

        // ── Gráficos ─────────────────────────────────────────────

        // Horas por médico (top 10)
        $horasPorMedico = TurnoMedico::whereYear('fecha',$anio)->whereMonth('fecha',$mes)
            ->where('fue_laborado',true)
            ->join('medicos','medicos.id','=','turno_medicos.medico_id')
            ->selectRaw('medicos.nombre, medicos.apellido, SUM(COALESCE(turno_medicos.horas_reconocidas, turno_medicos.horas_total)) as total')
            ->groupBy('medicos.id','medicos.nombre','medicos.apellido')
            ->orderByDesc('total')->limit(10)->get()
            ->map(fn($r) => [
                'nombre' => trim($r->nombre.' '.$r->apellido),
                'horas'  => round($r->total, 1),
            ]);

        // Horas por UCI
        $horasPorUci = TurnoMedico::whereYear('fecha',$anio)->whereMonth('fecha',$mes)
            ->where('fue_laborado',true)
            ->join('ucis','ucis.id','=','turno_medicos.uci_id')
            ->selectRaw('ucis.nombre as uci_nombre, SUM(COALESCE(turno_medicos.horas_reconocidas, turno_medicos.horas_total)) as total')
            ->groupBy('ucis.id','ucis.nombre')
            ->orderByDesc('total')->get()
            ->map(fn($r) => ['nombre'=>$r->uci_nombre,'horas'=>round($r->total,1)]);

        // Distribución de códigos de turno
        $distribucionTurnos = TurnoMedico::whereYear('fecha',$anio)->whereMonth('fecha',$mes)
            ->whereNotIn('codigo_turno',['','LIBRE'])
            ->selectRaw('codigo_turno, COUNT(*) as total')
            ->groupBy('codigo_turno')->orderByDesc('total')->get()
            ->pluck('total','codigo_turno');

        // Solicitudes por estado
        $solicitudesPorEstado = SolicitudCambioTurno::selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')->get()->pluck('total','estado');

        // Novedades por tipo
        $novedadesPorTipo = Novedad::whereYear('fecha',$anio)->whereMonth('fecha',$mes)
            ->selectRaw('tipo_novedad, COUNT(*) as total')
            ->groupBy('tipo_novedad')->get()->pluck('total','tipo_novedad');

        // Ranking médicos (top 10 horas)
        $rankingMedicos = $horasPorMedico;

        return view('dashboard.index', compact(
            'archivos','archivo','archivoId','mes','anio','meses',
            'totalMedicos','horasProgramadas','horasReconocidas','horasRestadas',
            'medicosExceso200','alertasAbiertas','alertas12HHabil',
            'turnosOfrecidos','cambiosPendientes','turnosDescubiertos','novedadesMes',
            'horasPorMedico','horasPorUci','distribucionTurnos',
            'solicitudesPorEstado','novedadesPorTipo','rankingMedicos'
        ));
    }

    private function medicosConExceso200(int $mes, int $anio): int
    {
        $medicos = Medico::where('activo',true)->pluck('id');
        $count   = 0;
        foreach ($medicos as $id) {
            $h = TurnoMedico::where('medico_id',$id)
                ->whereYear('fecha',$anio)->whereMonth('fecha',$mes)
                ->where('fue_laborado',true)
                ->sum(DB::raw('COALESCE(horas_reconocidas, horas_total)'));
            if ($h > 200) $count++;
        }
        return $count;
    }
}
