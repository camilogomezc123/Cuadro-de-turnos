<?php

namespace App\Http\Controllers;

use App\Models\SemanaMolde;
use App\Models\SemanaMoldeDetalle;
use App\Models\Medico;
use App\Models\TurnoMedico;
use App\Models\ArchivoCargado;
use App\Models\Uci;
use App\Services\TurnoService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SemanaMoldeController extends Controller
{
    public function index()
    {
        $semanas = SemanaMolde::with(['uci', 'detalles'])->orderBy('nombre')->paginate(20);
        $ucis    = Uci::where('activa', true)->orderBy('nombre')->get();
        return view('semanas-molde.index', compact('semanas', 'ucis'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
            'uci_id'      => 'nullable|exists:ucis,id',
            'turnos'      => 'required|array',
            'turnos.*'    => 'required|string|max:10',
        ]);

        $semana = SemanaMolde::create([
            'nombre'      => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'uci_id'      => $data['uci_id'] ?? null,
            'activa'      => true,
        ]);

        $diasSemana = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
        foreach ($diasSemana as $dia) {
            SemanaMoldeDetalle::create([
                'semana_molde_id' => $semana->id,
                'dia_semana'      => $dia,
                'codigo_turno'    => strtoupper($data['turnos'][$dia] ?? 'LIBRE'),
            ]);
        }

        return back()->with('success', "Semana molde '{$semana->nombre}' creada correctamente.");
    }

    public function destroy(SemanaMolde $semanaMolde)
    {
        $semanaMolde->delete();
        return back()->with('success', 'Semana molde eliminada.');
    }

    /** Aplicar semana molde a médicos/UCI en un rango de fechas */
    public function aplicar(Request $request, SemanaMolde $semanaMolde)
    {
        $data = $request->validate([
            'archivo_id'  => 'required|exists:archivos_cargados,id',
            'medico_ids'  => 'required|array|min:1',
            'medico_ids.*'=> 'exists:medicos,id',
        ]);

        $archivo    = ArchivoCargado::findOrFail($data['archivo_id']);
        $semanaMolde->load('detalles');
        $aplicados  = 0;

        foreach ($data['medico_ids'] as $medicoId) {
            // Obtener todos los turnos del médico en el mes
            $turnos = TurnoMedico::where('archivo_id', $archivo->id)
                ->where('medico_id', $medicoId)
                ->get();

            foreach ($turnos as $turno) {
                $fecha = Carbon::parse($turno->fecha);
                // Obtener el día de semana en español
                $diaMap = [1=>'lunes',2=>'martes',3=>'miercoles',4=>'jueves',5=>'viernes',6=>'sabado',0=>'domingo'];
                $diaSemana = $diaMap[$fecha->dayOfWeek];
                $codigoMolde = $semanaMolde->turnoParaDia($diaSemana);

                $horas = TurnoService::horasPorCodigo($codigoMolde);
                $turno->update([
                    'codigo_turno'        => $codigoMolde,
                    'horas_diurnas'       => $horas['diurnas'],
                    'horas_nocturnas'     => $horas['nocturnas'],
                    'horas_total'         => $horas['total'],
                    'editado_manualmente' => true,
                    'editado_por'         => 'semana_molde',
                    'editado_at'          => now(),
                ]);
                $aplicados++;
            }
        }

        return back()->with('success', "Semana molde aplicada. {$aplicados} turnos actualizados.");
    }
}
