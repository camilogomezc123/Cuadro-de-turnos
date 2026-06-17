<?php

namespace App\Services;

use App\Models\ArchivoCargado;
use App\Models\IndicadorMedico;
use App\Models\IndicadorUci;
use App\Models\Medico;
use App\Models\TurnoMedico;
use App\Models\Uci;
use Illuminate\Support\Facades\DB;

class TurnoCalculatorService
{
    /**
     * Persiste los turnos parseados y calcula todos los indicadores.
     */
    public function procesarYPersistir(ArchivoCargado $archivo, array $datosUcis, int $mes, int $anio): void
    {
        DB::transaction(function () use ($archivo, $datosUcis, $mes, $anio) {
            $totalMedicos = 0;
            $totalTurnos  = 0;
            $diasEnMes    = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

            foreach ($datosUcis as $codigoUci => $datosUci) {
                $uci = Uci::where('codigo', $codigoUci)->first();
                if (!$uci) continue;

                $contadorDiasConTurno   = array_fill(1, $diasEnMes, false);
                $contadorDiasFinde      = array_fill(1, $diasEnMes, false);
                $contadorDiasNoche      = array_fill(1, $diasEnMes, false);
                $diasFinDeSemanaEnMes   = $this->contarDiasTipo($mes, $anio, 'finde');
                $diasNocheEnMes         = $diasEnMes;

                foreach ($datosUci['medicos'] as $nombreMedico => $turnos) {
                    $medico = Medico::firstOrCreate(
                        ['nombre' => $nombreMedico, 'uci_id' => $uci->id],
                        ['activo' => true]
                    );

                    $turnosGuardar = [];
                    foreach ($turnos as $turno) {
                        $row = [
                            'archivo_id'         => $archivo->id,
                            'medico_id'          => $medico->id,
                            'uci_id'             => $uci->id,
                            'fecha'              => $turno['fecha'],
                            'dia_numero'         => $turno['dia'],
                            'dia_semana'         => $turno['dia_semana'],
                            'codigo_turno'       => $turno['codigo_turno'],
                            'horas_diurnas'      => $turno['horas_diurnas'],
                            'horas_nocturnas'    => $turno['horas_nocturnas'],
                            'horas_total'        => $turno['horas_total'],
                            'es_fin_semana'      => $turno['es_fin_semana'],
                            'es_domingo'         => $turno['es_domingo'],
                            'fila_excel'         => $turno['fila_excel'] ?? null,
                            'columna_excel'      => $turno['columna_excel'] ?? null,
                            'estado_validacion'  => empty($turno['observacion']) ? 'ok' : 'advertencia',
                            'observacion'        => $turno['observacion'] ?? null,
                            'es_codigo_no_oficial' => $turno['es_codigo_no_oficial'] ?? false,
                            'created_at'         => now(),
                            'updated_at'         => now(),
                        ];
                        $turnosGuardar[] = $row;
                        $totalTurnos++;

                        // Marcar cobertura para la UCI
                        if ($turno['horas_total'] > 0) {
                            $contadorDiasConTurno[$turno['dia']] = true;
                            if ($turno['es_fin_semana']) $contadorDiasFinde[$turno['dia']] = true;
                            if ($turno['horas_nocturnas'] > 0) $contadorDiasNoche[$turno['dia']] = true;
                        }
                    }

                    TurnoMedico::insert($turnosGuardar);
                    $this->calcularIndicadorMedico($archivo, $medico, $uci, $mes, $anio, $turnos, $diasEnMes);
                    $totalMedicos++;
                }

                $this->calcularIndicadorUci(
                    $archivo, $uci, $mes, $anio, $datosUci['medicos'],
                    $diasEnMes, $diasFinDeSemanaEnMes,
                    $contadorDiasConTurno, $contadorDiasFinde, $contadorDiasNoche
                );
            }

            $archivo->update([
                'procesado'     => true,
                'total_medicos' => $totalMedicos,
                'total_turnos'  => $totalTurnos,
            ]);
        });
    }

    private function calcularIndicadorMedico(
        ArchivoCargado $archivo,
        Medico $medico,
        Uci $uci,
        int $mes, int $anio,
        array $turnos,
        int $diasEnMes
    ): void {
        $totalHoras     = 0;
        $horasDiurnas   = 0;
        $horasNocturnas = 0;
        $turnosM = $turnosT = $turnosMT = $turnosN = 0;
        $turnosFinde = $turnosDomingo = 0;

        foreach ($turnos as $t) {
            $totalHoras     += $t['horas_total'];
            $horasDiurnas   += $t['horas_diurnas'];
            $horasNocturnas += $t['horas_nocturnas'];

            // MN y MTN cuentan como M+N y MT+N respectivamente
            match ($t['codigo_turno']) {
                'M'   => $turnosM++,
                'T'   => $turnosT++,
                'MT'  => $turnosMT++,
                'N'   => $turnosN++,
                'MN'  => ($turnosM++ && $turnosN++),
                'MTN' => ($turnosMT++ && $turnosN++),
                default => null,
            };

            if ($t['horas_total'] > 0 && $t['es_fin_semana']) $turnosFinde++;
            if ($t['horas_total'] > 0 && $t['es_domingo'])    $turnosDomingo++;
        }

        $semanasEnMes    = $diasEnMes / 7;
        $horasPosibles   = 24 * $diasEnMes;
        $promedioSemanal = $semanasEnMes > 0 ? round($totalHoras / $semanasEnMes, 2) : 0;
        $promedioDiario  = $diasEnMes > 0 ? round($totalHoras / $diasEnMes, 2) : 0;
        $pctOcupacion    = $horasPosibles > 0 ? round(($totalHoras / $horasPosibles) * 100, 2) : 0;

        IndicadorMedico::updateOrCreate(
            ['medico_id' => $medico->id, 'uci_id' => $uci->id, 'mes' => $mes, 'anio' => $anio],
            [
                'archivo_id'          => $archivo->id,
                'total_horas'         => $totalHoras,
                'horas_diurnas'       => $horasDiurnas,
                'horas_nocturnas'     => $horasNocturnas,
                'turnos_m'            => $turnosM,
                'turnos_t'            => $turnosT,
                'turnos_mt'           => $turnosMT,
                'turnos_n'            => $turnosN,
                'turnos_fin_semana'   => $turnosFinde,
                'turnos_domingo'      => $turnosDomingo,
                'promedio_semanal'    => $promedioSemanal,
                'promedio_diario'     => $promedioDiario,
                'porcentaje_ocupacion'=> $pctOcupacion,
            ]
        );
    }

    private function calcularIndicadorUci(
        ArchivoCargado $archivo,
        Uci $uci,
        int $mes, int $anio,
        array $medicosData,
        int $diasEnMes,
        int $diasFinDeSemanaEnMes,
        array $contadorDiasConTurno,
        array $contadorDiasFinde,
        array $contadorDiasNoche
    ): void {
        $numEspecialistas  = count($medicosData);
        $horasTotales      = 0;

        foreach ($medicosData as $turnos) {
            foreach ($turnos as $t) {
                $horasTotales += $t['horas_total'];
            }
        }

        $horasPromedioMedico = $numEspecialistas > 0 ? round($horasTotales / $numEspecialistas, 1) : 0;

        $diasCubiertos   = count(array_filter($contadorDiasConTurno));
        $findeCubiertos  = count(array_filter($contadorDiasFinde));
        $nocheCubiertos  = count(array_filter($contadorDiasNoche));

        $coberturaMensual  = $diasEnMes > 0 ? round(($diasCubiertos / $diasEnMes) * 100, 2) : 0;
        $coberturaFinde    = $diasFinDeSemanaEnMes > 0 ? round(($findeCubiertos / $diasFinDeSemanaEnMes) * 100, 2) : 0;
        $coberturaNocturna = $diasEnMes > 0 ? round(($nocheCubiertos / $diasEnMes) * 100, 2) : 0;

        $horasDiurnasTotales   = 0;
        $horasNocturnasTotales = 0;
        foreach ($medicosData as $turnos) {
            foreach ($turnos as $t) {
                $horasDiurnasTotales   += $t['horas_diurnas'];
                $horasNocturnasTotales += $t['horas_nocturnas'];
            }
        }
        $cargaDiurnaPct   = $horasTotales > 0 ? round(($horasDiurnasTotales / $horasTotales) * 100, 2) : 0;
        $cargaNocturnaPct = $horasTotales > 0 ? round(($horasNocturnasTotales / $horasTotales) * 100, 2) : 0;

        IndicadorUci::updateOrCreate(
            ['uci_id' => $uci->id, 'mes' => $mes, 'anio' => $anio],
            [
                'archivo_id'           => $archivo->id,
                'num_especialistas'    => $numEspecialistas,
                'horas_totales'        => $horasTotales,
                'horas_promedio_medico'=> $horasPromedioMedico,
                'cobertura_mensual'    => $coberturaMensual,
                'cobertura_fin_semana' => $coberturaFinde,
                'cobertura_nocturna'   => $coberturaNocturna,
                'carga_diurna_pct'     => $cargaDiurnaPct,
                'carga_nocturna_pct'   => $cargaNocturnaPct,
            ]
        );
    }

    /**
     * Recalcula indicadores de un archivo ya existente (creado manualmente o por plantilla).
     */
    public function recalcularIndicadores(ArchivoCargado $archivo): void
    {
        $mes  = $archivo->mes;
        $anio = $archivo->anio;
        $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

        // Limpiar indicadores anteriores
        $archivo->indicadorMedicos()->delete();
        $archivo->indicadorUcis()->delete();

        $turnos = TurnoMedico::where('archivo_id', $archivo->id)->get();
        $totalMedicos = 0;
        $totalTurnos  = 0;

        // Agrupar por UCI y médico
        $porUciMedico = [];
        foreach ($turnos as $t) {
            $porUciMedico[$t->uci_id][$t->medico_id][] = $t;
            $totalTurnos++;
        }

        $ucisIds = array_keys($porUciMedico);
        $ucis    = Uci::whereIn('id', $ucisIds)->get()->keyBy('id');
        $medIds  = $turnos->pluck('medico_id')->unique();
        $medicos = Medico::whereIn('id', $medIds)->get()->keyBy('id');

        foreach ($porUciMedico as $uciId => $medicosData) {
            $uci = $ucis[$uciId] ?? null;
            if (!$uci) continue;

            $totalMedicos += count($medicosData);

            foreach ($medicosData as $medicoId => $listaTurnos) {
                $medico = $medicos[$medicoId] ?? null;
                if (!$medico) continue;
                $this->calcularIndicadorMedico($archivo, $medico, $uci, $mes, $anio, $listaTurnos, $diasEnMes);
            }

            $this->calcularIndicadorUci($archivo, $uci, $mes, $anio,
                collect(array_merge(...array_values($medicosData))), $diasEnMes);
        }

        $archivo->update([
            'procesado'     => true,
            'total_medicos' => $totalMedicos,
            'total_turnos'  => $totalTurnos,
        ]);
    }

    private function contarDiasTipo(int $mes, int $anio, string $tipo): int
    {
        $dias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        $count = 0;
        for ($d = 1; $d <= $dias; $d++) {
            $fecha = \Carbon\Carbon::create($anio, $mes, $d);
            if ($tipo === 'finde' && in_array($fecha->dayOfWeek, [0, 6])) $count++;
        }
        return $count;
    }
}
