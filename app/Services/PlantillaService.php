<?php

namespace App\Services;

use App\Models\ArchivoCargado;
use App\Models\Medico;
use App\Models\TurnoMedico;
use App\Models\IndicadorMedico;
use App\Models\IndicadorUci;
use App\Models\Uci;
use Carbon\Carbon;

class PlantillaService
{
    const HORAS = [
        'M'    => ['diurnas' => 6.0,  'nocturnas' => 0.0,  'total' => 6.0],
        'T'    => ['diurnas' => 6.0,  'nocturnas' => 0.0,  'total' => 6.0],
        'MT'   => ['diurnas' => 12.0, 'nocturnas' => 0.0,  'total' => 12.0],
        'N'    => ['diurnas' => 0.0,  'nocturnas' => 12.0, 'total' => 12.0],
        'MTN'  => ['diurnas' => 12.0, 'nocturnas' => 12.0, 'total' => 24.0],
        'MN'   => ['diurnas' => 6.0,  'nocturnas' => 12.0, 'total' => 18.0],
        'VAC'  => ['diurnas' => 0.0,  'nocturnas' => 0.0,  'total' => 0.0],
        'PER'  => ['diurnas' => 0.0,  'nocturnas' => 0.0,  'total' => 0.0],
        'INC'  => ['diurnas' => 0.0,  'nocturnas' => 0.0,  'total' => 0.0],
        'LIBRE'=> ['diurnas' => 0.0,  'nocturnas' => 0.0,  'total' => 0.0],
        ''     => ['diurnas' => 0.0,  'nocturnas' => 0.0,  'total' => 0.0],
    ];

    private TurnoCalculatorService $calculator;

    public function __construct(TurnoCalculatorService $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Toma los turnos del archivo base y genera todos los meses del año destino
     * repitiendo el patrón semanal detectado de cada médico.
     * Retorna array con lista de archivos generados.
     */
    public function repetirAnio(int $archivoBaseId, int $anioDestino): array
    {
        $base = ArchivoCargado::findOrFail($archivoBaseId);

        // Construir patrón semanal por médico+UCI
        // patron[medicoId][uciId][dow] = codigo_turno (dow: 0=Lun, 6=Dom)
        $patrones = [];
        $turnosBase = TurnoMedico::where('archivo_id', $archivoBaseId)->get();

        foreach ($turnosBase as $t) {
            if (empty($t->codigo_turno)) continue;
            $fecha = Carbon::parse($t->fecha);
            $dow   = ($fecha->dayOfWeek === 0) ? 6 : $fecha->dayOfWeek - 1;

            if (!isset($patrones[$t->medico_id][$t->uci_id][$dow])) {
                $patrones[$t->medico_id][$t->uci_id][$dow] = [];
            }
            $patrones[$t->medico_id][$t->uci_id][$dow][] = $t->codigo_turno;
        }

        // Para cada dow, usar el código más frecuente
        $patronFinal = [];
        foreach ($patrones as $medicoId => $ucisData) {
            foreach ($ucisData as $uciId => $dowData) {
                foreach ($dowData as $dow => $codigos) {
                    $contar = array_count_values($codigos);
                    arsort($contar);
                    $patronFinal[$medicoId][$uciId][$dow] = array_key_first($contar);
                }
            }
        }

        $archivosCreados = [];
        $nombresMeses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                         'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        for ($mes = 1; $mes <= 12; $mes++) {
            // Saltar el mes base si es el mismo año
            if ($mes == $base->mes && $anioDestino == $base->anio) continue;

            // Eliminar archivo existente para ese mes/año si lo hay
            $existente = ArchivoCargado::where('mes', $mes)->where('anio', $anioDestino)->first();
            if ($existente) {
                $existente->turnoMedicos()->delete();
                $existente->indicadorMedicos()->delete();
                $existente->indicadorUcis()->delete();
                $existente->delete();
            }

            $archivo = ArchivoCargado::create([
                'nombre_archivo' => "Generado_Patron_{$nombresMeses[$mes]}_{$anioDestino}.xlsx",
                'ruta'           => '',
                'mes'            => $mes,
                'anio'           => $anioDestino,
                'procesado'      => false,
            ]);

            $diasEnMes  = cal_days_in_month(CAL_GREGORIAN, $mes, $anioDestino);
            $turnosData = [];

            foreach ($patronFinal as $medicoId => $ucisData) {
                foreach ($ucisData as $uciId => $patron) {
                    for ($dia = 1; $dia <= $diasEnMes; $dia++) {
                        $fecha  = Carbon::create($anioDestino, $mes, $dia);
                        $dow    = ($fecha->dayOfWeek === 0) ? 6 : $fecha->dayOfWeek - 1;
                        $codigo = $patron[$dow] ?? '';
                        $horas  = self::HORAS[$codigo] ?? self::HORAS[''];

                        $turnosData[] = [
                            'archivo_id'      => $archivo->id,
                            'medico_id'       => $medicoId,
                            'uci_id'          => $uciId,
                            'fecha'           => $fecha->toDateString(),
                            'dia_semana'      => $this->dowNombre($dow),
                            'codigo_turno'    => $codigo,
                            'horas_diurnas'   => $horas['diurnas'],
                            'horas_nocturnas' => $horas['nocturnas'],
                            'horas_total'     => $horas['total'],
                            'es_fin_semana'   => in_array($fecha->dayOfWeek, [0, 6]),
                            'es_domingo'      => $fecha->dayOfWeek === 0,
                            'tiene_alerta'    => false,
                        ];
                    }
                }
            }

            // Insertar en bloques
            foreach (array_chunk($turnosData, 500) as $chunk) {
                TurnoMedico::insert($chunk);
            }

            // Recalcular indicadores
            $this->calculator->recalcularIndicadores($archivo);

            $archivosCreados[] = [
                'mes'    => $mes,
                'anio'   => $anioDestino,
                'nombre' => $nombresMeses[$mes] . ' ' . $anioDestino,
                'id'     => $archivo->id,
            ];
        }

        return $archivosCreados;
    }

    /**
     * Crea un mes desde datos manuales pasados como array:
     * [ medicoId => [ dia => codigoTurno ], ... ]
     */
    public function crearDesdeManual(
        int $uciId, int $mes, int $anio, array $turnos, bool $sobreescribir = false
    ): ArchivoCargado {
        $existente = ArchivoCargado::where('mes', $mes)->where('anio', $anio)->first();
        if ($existente && !$sobreescribir) {
            // Comprobar si ya tiene turnos de esta UCI
            $tieneUci = TurnoMedico::where('archivo_id', $existente->id)
                ->where('uci_id', $uciId)->exists();
            if ($tieneUci) {
                // Borrar solo turnos de esa UCI
                TurnoMedico::where('archivo_id', $existente->id)
                    ->where('uci_id', $uciId)->delete();
            }
            $archivo = $existente;
        } elseif ($existente && $sobreescribir) {
            $existente->turnoMedicos()->where('uci_id', $uciId)->delete();
            $archivo = $existente;
        } else {
            $nombresMeses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                             'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $archivo = ArchivoCargado::create([
                'nombre_archivo' => "Manual_{$nombresMeses[$mes]}_{$anio}.xlsx",
                'ruta'           => '',
                'mes'            => $mes,
                'anio'           => $anio,
                'procesado'      => false,
            ]);
        }

        $diasEnMes  = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        $turnosData = [];

        foreach ($turnos as $medicoId => $diasTurno) {
            for ($dia = 1; $dia <= $diasEnMes; $dia++) {
                $codigo = strtoupper(trim($diasTurno[$dia] ?? ''));
                $horas  = self::HORAS[$codigo] ?? self::HORAS[''];
                $fecha  = Carbon::create($anio, $mes, $dia);
                $dow    = ($fecha->dayOfWeek === 0) ? 6 : $fecha->dayOfWeek - 1;

                $turnosData[] = [
                    'archivo_id'      => $archivo->id,
                    'medico_id'       => $medicoId,
                    'uci_id'          => $uciId,
                    'fecha'           => $fecha->toDateString(),
                    'dia_semana'      => $this->dowNombre($dow),
                    'codigo_turno'    => $codigo,
                    'horas_diurnas'   => $horas['diurnas'],
                    'horas_nocturnas' => $horas['nocturnas'],
                    'horas_total'     => $horas['total'],
                    'es_fin_semana'   => in_array($fecha->dayOfWeek, [0, 6]),
                    'es_domingo'      => $fecha->dayOfWeek === 0,
                    'tiene_alerta'    => false,
                ];
            }
        }

        foreach (array_chunk($turnosData, 500) as $chunk) {
            TurnoMedico::insert($chunk);
        }

        $this->calculator->recalcularIndicadores($archivo);

        return $archivo;
    }

    private function dowNombre(int $dow): string
    {
        return ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'][$dow] ?? 'lunes';
    }
}
