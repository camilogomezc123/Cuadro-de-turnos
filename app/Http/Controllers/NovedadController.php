<?php

namespace App\Http\Controllers;

use App\Models\Novedad;
use App\Models\TurnoMedico;
use App\Models\Medico;
use App\Models\AuditoriaSistema;
use App\Services\HoraConsolidadoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NovedadController extends Controller
{
    public function __construct(private HoraConsolidadoService $horaService) {}

    public function index(Request $request)
    {
        $mes   = (int)($request->mes  ?? now()->month);
        $anio  = (int)($request->anio ?? now()->year);
        $tipo  = $request->tipo;

        $query = Novedad::with(['medico','uci','turno'])
            ->whereYear('fecha', $anio)->whereMonth('fecha', $mes)
            ->orderByDesc('created_at');

        if ($tipo) $query->where('tipo_novedad', $tipo);

        $novedades  = $query->paginate(25);
        $tipos      = Novedad::TIPOS;
        $meses      = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                       'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        return view('novedades.index', compact('novedades','tipos','mes','anio','meses'));
    }

    // Registrar no asistencia a un turno (quita horas)
    public function registrarNoAsistencia(Request $request, TurnoMedico $turno)
    {
        $request->validate([
            'descripcion'           => 'nullable|string|max:500',
            'medico_reemplazo_id'   => 'nullable|exists:medicos,id',
            'codigo_reemplazo'      => 'nullable|string|max:10',
            'estado_resultado'      => 'required|in:descubierto,reemplazado,pendiente_por_cubrir',
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($request, $turno, $user) {
            $horasOriginales = $turno->horas_total;

            // Marcar turno como no asistido
            $turno->update([
                'estado_turno'            => 'no_asistido',
                'fue_laborado'            => false,
                'horas_reconocidas'       => 0,
                'medico_original_id'      => $turno->medico_id,
                'motivo_modificacion'     => $request->descripcion,
                'modificado_por_usuario_id'=> $user->id,
                'fecha_modificacion'      => now(),
            ]);

            // Si hay reemplazo: crear turno al médico reemplazo
            if ($request->medico_reemplazo_id) {
                $codigoReem = strtoupper($request->codigo_reemplazo ?: $turno->codigo_turno);
                $horasReem  = TurnoMedico::horasPorCodigo($codigoReem);

                TurnoMedico::updateOrCreate(
                    [
                        'archivo_id' => $turno->archivo_id,
                        'medico_id'  => $request->medico_reemplazo_id,
                        'uci_id'     => $turno->uci_id,
                        'fecha'      => $turno->fecha,
                    ],
                    [
                        'dia_numero'      => $turno->dia_numero,
                        'dia_semana'      => $turno->dia_semana,
                        'codigo_turno'    => $codigoReem,
                        'horas_total'     => $horasReem,
                        'horas_reconocidas'=> $horasReem,
                        'horas_diurnas'   => in_array($codigoReem,['M','T','MT','MTN']) ? min($horasReem,12) : 0,
                        'horas_nocturnas' => in_array($codigoReem,['N','MTN','MN']) ? 12 : 0,
                        'es_fin_semana'   => $turno->es_fin_semana,
                        'es_domingo'      => $turno->es_domingo,
                        'estado_turno'    => 'reemplazado',
                        'fue_laborado'    => true,
                        'medico_original_id'=> $turno->medico_id,
                    ]
                );

                $turno->update([
                    'estado_turno'        => 'reemplazado',
                    'medico_reemplazo_id' => $request->medico_reemplazo_id,
                ]);
            } elseif ($request->estado_resultado === 'descubierto') {
                $turno->update(['estado_turno' => 'descubierto']);
            }

            // Crear novedad
            Novedad::create([
                'medico_id'          => $turno->medico_id,
                'turno_id'           => $turno->id,
                'uci_id'             => $turno->uci_id,
                'fecha'              => $turno->fecha,
                'tipo_novedad'       => 'no_asistencia',
                'descripcion'        => $request->descripcion ?: 'No asistencia registrada por usuario maestro.',
                'horas_afectadas'    => $horasOriginales,
                'resta_horas'        => true,
                'usuario_maestro_id' => $user->id,
                'visible_para_medico'=> false,
                'estado'             => 'activa',
            ]);

            // Auditoría
            AuditoriaSistema::registrar(
                'NO_ASISTENCIA', 'turnos', 'TurnoMedico', $turno->id,
                ['estado_turno' => 'programado', 'horas' => $horasOriginales],
                ['estado_turno' => 'no_asistido', 'horas_reconocidas' => 0],
                $request->descripcion ?? 'No asistencia',
                $user->name
            );
        });

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'mensaje' => 'No asistencia registrada y horas descontadas.']);
        }

        return back()->with('success', 'No asistencia registrada. Las horas han sido descontadas.');
    }

    // Crear novedad manual desde el panel maestro
    public function store(Request $request)
    {
        $request->validate([
            'medico_id'          => 'required|exists:medicos,id',
            'fecha'              => 'required|date',
            'tipo_novedad'       => 'required|in:' . implode(',', array_keys(Novedad::TIPOS)),
            'descripcion'        => 'nullable|string|max:1000',
            'horas_afectadas'    => 'nullable|numeric|min:0|max:24',
            'resta_horas'        => 'boolean',
            'visible_para_medico'=> 'boolean',
        ]);

        Novedad::create([
            ...$request->only(['medico_id','fecha','tipo_novedad','descripcion','horas_afectadas','resta_horas','visible_para_medico']),
            'usuario_maestro_id' => Auth::id(),
            'estado'             => 'activa',
        ]);

        AuditoriaSistema::registrar(
            'CREAR_NOVEDAD', 'novedades', 'Novedad', null,
            null, $request->only(['medico_id','tipo_novedad','fecha']),
            $request->descripcion ?? '', Auth::user()->name
        );

        return back()->with('success', 'Novedad registrada correctamente.');
    }

    public function update(Request $request, Novedad $novedad)
    {
        $request->validate([
            'estado'      => 'required|in:activa,resuelta,anulada',
            'descripcion' => 'nullable|string|max:1000',
        ]);

        $novedad->update($request->only(['estado','descripcion','visible_para_medico']));
        return back()->with('success', 'Novedad actualizada.');
    }

    public function destroy(Novedad $novedad)
    {
        $novedad->update(['estado' => 'anulada']);
        return back()->with('success', 'Novedad anulada.');
    }
}
