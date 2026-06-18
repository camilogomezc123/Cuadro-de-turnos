<?php

namespace App\Services;

use App\Models\TurnoMedico;
use App\Models\Medico;
use App\Models\Novedad;
use App\Models\AlertaTurno;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HoraConsolidadoService
{
    // Resumen completo de horas para un médico en un mes (MULTI-UCI)
    public function resumenMedico(int $medicoId, int $mes, int $anio): array
    {
        $turnos = TurnoMedico::where('medico_id', $medicoId)
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->get();

        $horasProgramadas = $turnos->sum('horas_total');
        $horasReconocidas = $turnos->sum(fn($t) => $t->fue_laborado ? ($t->horas_reconocidas ?? $t->horas_total ?? 0) : 0);

        return [
            'medico_id'           => $medicoId,
            'mes'                 => $mes,
            'anio'                => $anio,
            'horas_programadas'   => $horasProgramadas,
            'horas_reconocidas'   => $horasReconocidas,
            'horas_diurnas'       => $turnos->where('fue_laborado',true)->sum('horas_diurnas'),
            'horas_nocturnas'     => $turnos->where('fue_laborado',true)->sum('horas_nocturnas'),
            'turnos_M'            => $turnos->where('codigo_turno','M')->count(),
            'turnos_T'            => $turnos->where('codigo_turno','T')->count(),
            'turnos_MT'           => $turnos->where('codigo_turno','MT')->count(),
            'turnos_N'            => $turnos->where('codigo_turno','N')->count(),
            'turnos_MTN'          => $turnos->where('codigo_turno','MTN')->count(),
            'turnos_MN'           => $turnos->where('codigo_turno','MN')->count(),
            'total_domingos'      => $turnos->where('es_domingo',true)->where('fue_laborado',true)->count(),
            'total_fines_semana'  => $turnos->where('es_fin_semana',true)->where('fue_laborado',true)->count(),
            'ucis_trabajadas'     => $turnos->pluck('uci_id')->unique()->count(),
            'supera_200h'         => $horasReconocidas > 200,
            'estado_carga'        => $this->estadoCarga($horasReconocidas),
        ];
    }

    // Consolidado de TODOS los médicos para un mes
    public function consolidadoMensual(int $mes, int $anio): Collection
    {
        $medicos = Medico::where('activo', true)->orderBy('nombre')->get();

        return $medicos->map(function (Medico $m) use ($mes, $anio) {
            $resumen = $this->resumenMedico($m->id, $mes, $anio);

            // Novedades del mes
            $novedades = Novedad::where('medico_id', $m->id)
                ->whereYear('fecha', $anio)->whereMonth('fecha', $mes)->count();

            // Alertas
            $alertas = AlertaTurno::where('medico_id', $m->id)
                ->where('estado', 'abierta')
                ->whereYear('created_at', $anio)->whereMonth('created_at', $mes)->count();

            return array_merge($resumen, [
                'medico'      => $m,
                'novedades'   => $novedades,
                'alertas'     => $alertas,
            ]);
        });
    }

    // Generar alertas 200h y 12h-hábil para todos los médicos de un mes
    public function generarAlertasMes(int $mes, int $anio): array
    {
        $alertasCreadas = 0;
        $medicos        = Medico::where('activo', true)->get();

        foreach ($medicos as $medico) {
            // Alerta 200h
            $total = TurnoMedico::where('medico_id', $medico->id)
                ->whereYear('fecha', $anio)->whereMonth('fecha', $mes)
                ->where('fue_laborado', true)
                ->sum(DB::raw('COALESCE(horas_reconocidas, horas_total)'));

            if ($total > 200) {
                $existe = AlertaTurno::where('medico_id', $medico->id)
                    ->where('tipo', 'EXCESO_200H')
                    ->whereYear('created_at', $anio)->whereMonth('created_at', $mes)
                    ->exists();

                if (!$existe) {
                    AlertaTurno::create([
                        'medico_id'   => $medico->id,
                        'archivo_id'  => null,
                        'tipo'        => 'EXCESO_200H',
                        'prioridad'   => 'alta',
                        'mensaje'     => "El médico {$medico->nombre_completo} supera las 200 horas programadas en el mes {$mes}/{$anio}. Total: {$total}h. Validar carga laboral.",
                        'mensaje_medico'=> "Usted supera las 200 horas programadas en el mes. Revise su programación con coordinación.",
                        'estado'      => 'abierta',
                    ]);
                    $alertasCreadas++;
                }
            }

            // Alerta 12h en día hábil (agrupar por fecha)
            $diasConExceso = TurnoMedico::where('medico_id', $medico->id)
                ->whereYear('fecha', $anio)->whereMonth('fecha', $mes)
                ->where('fue_laborado', true)
                ->where('es_fin_semana', false)
                ->selectRaw('fecha, SUM(COALESCE(horas_reconocidas, horas_total)) as total_dia')
                ->groupBy('fecha')
                ->having('total_dia', '>', 12)
                ->get();

            foreach ($diasConExceso as $dia) {
                $existe = AlertaTurno::where('medico_id', $medico->id)
                    ->where('tipo', 'EXCESO_12H_HABIL')
                    ->where('fecha_turno', $dia->fecha)
                    ->exists();

                if (!$existe) {
                    AlertaTurno::create([
                        'medico_id'     => $medico->id,
                        'archivo_id'    => null,
                        'tipo'          => 'EXCESO_12H_HABIL',
                        'prioridad'     => 'alta',
                        'fecha_turno'   => $dia->fecha,
                        'mensaje'       => "El médico {$medico->nombre_completo} tiene {$dia->total_dia}h programadas el día {$dia->fecha} (día hábil, límite 12h). Validar y generar novedad si corresponde.",
                        'mensaje_medico'=> "Tiene más de 12 horas programadas en un día hábil ({$dia->fecha}). Revise su programación.",
                        'estado'        => 'abierta',
                    ]);
                    $alertasCreadas++;
                }
            }
        }

        return ['alertas_creadas' => $alertasCreadas];
    }

    private function estadoCarga(float $horas): string
    {
        if ($horas < 100) return 'bajo';
        if ($horas <= 200) return 'adecuado';
        return 'exceso';
    }
}
