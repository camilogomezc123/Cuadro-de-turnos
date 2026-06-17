<?php

namespace App\Services;

use App\Models\AlertaTurno;
use App\Models\TurnoMedico;
use App\Models\Ausencia;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AlertService
{
    const HORAS_MAX_SEMANA   = 60;
    const HORAS_MIN_MES      = 100;
    const HORAS_MAX_MES      = 240;
    const HORAS_MAX_JORNADA  = 12;

    /** Valida todos los turnos de un archivo después de importar */
    public function validarArchivo(int $archivoId): int
    {
        AlertaTurno::where('archivo_id', $archivoId)->delete();
        $alertasCreadas = 0;

        $turnos = TurnoMedico::with(['medico', 'uci'])
            ->where('archivo_id', $archivoId)
            ->orderBy('medico_id')
            ->orderBy('fecha')
            ->get();

        // Agrupar por médico
        $porMedico = $turnos->groupBy('medico_id');

        foreach ($porMedico as $medicoId => $turnosMedico) {
            $alertasCreadas += $this->validarMedicoEnMes($turnosMedico, $archivoId);
        }

        // Validar cobertura por UCI y día
        $porUci = $turnos->groupBy('uci_id');
        foreach ($porUci as $uciId => $turnosUci) {
            $alertasCreadas += $this->validarCoberturaUci($turnosUci, $archivoId, $uciId);
        }

        // Marcar turnos con alerta
        $idsConAlerta = AlertaTurno::where('archivo_id', $archivoId)
            ->whereNotNull('medico_id')
            ->pluck('medico_id')
            ->unique();

        TurnoMedico::where('archivo_id', $archivoId)
            ->whereIn('medico_id', $idsConAlerta)
            ->update(['tiene_alerta' => true]);

        return $alertasCreadas;
    }

    /** Valida el turno recién editado manualmente */
    public function validarTurnoEditado(TurnoMedico $turno): void
    {
        $turno->update(['tiene_alerta' => false]);

        // MTN en día hábil
        $fecha = Carbon::parse($turno->fecha);
        if ($turno->codigo_turno === 'MTN' && !in_array($fecha->dayOfWeek, [0, 6])) {
            $this->crearAlerta(
                archivoId:  $turno->archivo_id,
                medicoId:   $turno->medico_id,
                uciId:      $turno->uci_id,
                fecha:      $turno->fecha,
                tipo:       'MTN_DIA_HABIL',
                prioridad:  'alta',
                mensaje:    "El médico {$turno->medico->nombre} tiene turno MTN en día hábil ({$fecha->format('d/m/Y')})."
            );
            $turno->update(['tiene_alerta' => true]);
        }

        // Turno en período de ausencia
        if ($this->medicoTieneAusencia($turno->medico_id, $fecha)) {
            $this->crearAlerta(
                archivoId:  $turno->archivo_id,
                medicoId:   $turno->medico_id,
                uciId:      $turno->uci_id,
                fecha:      $turno->fecha,
                tipo:       'TURNO_EN_AUSENCIA',
                prioridad:  'alta',
                mensaje:    "El médico {$turno->medico->nombre} tiene una ausencia registrada para el {$fecha->format('d/m/Y')}. No se puede asignar turno."
            );
            $turno->update(['tiene_alerta' => true]);
        }

        // Jornada > 24h en día hábil
        if (!in_array($fecha->dayOfWeek, [0, 6]) && $turno->horas_total > 24) {
            $this->crearAlerta(
                archivoId:  $turno->archivo_id,
                medicoId:   $turno->medico_id,
                uciId:      $turno->uci_id,
                fecha:      $turno->fecha,
                tipo:       'JORNADA_24H_HABIL',
                prioridad:  'alta',
                mensaje:    "El médico {$turno->medico->nombre} tiene más de 24 horas asignadas en día hábil para la fecha {$fecha->format('d/m/Y')}. Validar programación y descanso laboral."
            );
            $turno->update(['tiene_alerta' => true]);
        }
    }

    // ──────────────────────────────────────────────
    // Métodos privados de validación
    // ──────────────────────────────────────────────

    private function validarMedicoEnMes(Collection $turnos, int $archivoId): int
    {
        $count   = 0;
        $primer  = $turnos->first();
        $medico  = $primer->medico;
        $uciId   = $primer->uci_id;
        $nombre  = $medico->nombre;

        $totalHoras     = $turnos->sum('horas_total');
        $horasDiurnas   = $turnos->sum('horas_diurnas');
        $horasNocturnas = $turnos->sum('horas_nocturnas');

        // 1. Menos de 100 horas mensuales
        if ($totalHoras < self::HORAS_MIN_MES && $totalHoras > 0) {
            $count += $this->crearAlerta($archivoId, $medico->id, $uciId, null, 'MES_DEFICIENTE', 'media',
                "El médico {$nombre} tiene solo {$totalHoras}h registradas (mínimo ".self::HORAS_MIN_MES."h).");
        }

        // 2. Supera horas máximas mensuales
        if ($totalHoras > self::HORAS_MAX_MES) {
            $count += $this->crearAlerta($archivoId, $medico->id, $uciId, null, 'MES_EXCESIVO', 'alta',
                "El médico {$nombre} supera las horas máximas mensuales: {$totalHoras}h (máx ".self::HORAS_MAX_MES."h).");
        }

        // 3. MTN en día hábil
        foreach ($turnos as $t) {
            if ($t->codigo_turno !== 'MTN') continue;
            $fecha = Carbon::parse($t->fecha);
            if (!in_array($fecha->dayOfWeek, [0, 6])) {
                $count += $this->crearAlerta($archivoId, $medico->id, $uciId, $t->fecha, 'MTN_DIA_HABIL', 'alta',
                    "El médico {$nombre} tiene turno MTN en día hábil ({$fecha->format('d/m/Y')}). MTN solo está permitido sábados y domingos.");
            }
        }

        // 4. Noche seguida de mañana (N un día, M/MT/MTN al día siguiente)
        $turnosPorFecha = $turnos->keyBy(fn($t) => $t->fecha->toDateString());
        foreach ($turnos as $t) {
            if (!in_array($t->codigo_turno, ['N', 'MTN'])) continue;
            $siguiente = Carbon::parse($t->fecha)->addDay()->toDateString();
            $tSig = $turnosPorFecha[$siguiente] ?? null;
            if ($tSig && in_array($tSig->codigo_turno, ['M', 'MT', 'MTN'])) {
                $count += $this->crearAlerta($archivoId, $medico->id, $uciId, $tSig->fecha, 'NOCHE_SEGUIDA_MANANA', 'media',
                    "El médico {$nombre} tiene noche el {$t->fecha->format('d/m')} y turno de mañana el {$tSig->fecha->format('d/m/Y')}. Sin descanso entre turnos.");
            }
        }

        // 5. Más de 60 horas semanales
        $porSemana = $turnos->groupBy(fn($t) => Carbon::parse($t->fecha)->weekOfYear);
        foreach ($porSemana as $semana => $turnosSemana) {
            $horasSemana = $turnosSemana->sum('horas_total');
            if ($horasSemana > self::HORAS_MAX_SEMANA) {
                $fechaRef = $turnosSemana->first()->fecha->format('d/m/Y');
                $count += $this->crearAlerta($archivoId, $medico->id, $uciId, $fechaRef, 'SEMANA_EXCESIVA', 'alta',
                    "El médico {$nombre} tiene {$horasSemana}h en la semana que incluye el {$fechaRef} (máx ".self::HORAS_MAX_SEMANA."h semanales).");
            }
        }

        // 6. Turno en período de ausencia aprobada
        foreach ($turnos as $t) {
            if ($t->horas_total == 0) continue;
            if ($this->medicoTieneAusencia($medico->id, Carbon::parse($t->fecha))) {
                $count += $this->crearAlerta($archivoId, $medico->id, $uciId, $t->fecha, 'TURNO_EN_AUSENCIA', 'alta',
                    "El médico {$nombre} tiene una ausencia registrada para el {$t->fecha->format('d/m/Y')}. Validar asignación.");
            }
        }

        // 7. Jornada > 24h en día hábil
        foreach ($turnos as $t) {
            $fecha = Carbon::parse($t->fecha);
            if (!in_array($fecha->dayOfWeek, [0, 6]) && $t->horas_total > 24) {
                $count += $this->crearAlerta($archivoId, $medico->id, $uciId, $t->fecha, 'JORNADA_24H_HABIL', 'alta',
                    "El médico {$nombre} tiene más de 24 horas asignadas en día hábil para la fecha {$fecha->format('d/m/Y')}. Validar programación y descanso laboral.");
            }
        }

        return $count;
    }

    private function validarCoberturaUci(Collection $turnos, int $archivoId, int $uciId): int
    {
        $count     = 0;
        $nombreUci = $turnos->first()->uci->nombre ?? "UCI #{$uciId}";

        // Agrupar por fecha
        $porFecha = $turnos->groupBy(fn($t) => $t->fecha->toDateString());

        foreach ($porFecha as $fechaStr => $turnosDia) {
            $fecha = Carbon::parse($fechaStr);

            // UCI sin cobertura nocturna
            $tieneNoche = $turnosDia->filter(fn($t) => in_array($t->codigo_turno, ['N','MTN','MN']))->isNotEmpty();
            if (!$tieneNoche) {
                $count += $this->crearAlerta($archivoId, null, $uciId, $fecha, 'SIN_COBERTURA_NOCHE', 'alta',
                    "La {$nombreUci} no tiene cobertura nocturna el {$fecha->format('d/m/Y')}.");
            }

            // UCI sin cobertura el domingo
            if ($fecha->dayOfWeek === 0) {
                $tieneCobertura = $turnosDia->filter(fn($t) => $t->horas_total > 0)->isNotEmpty();
                if (!$tieneCobertura) {
                    $count += $this->crearAlerta($archivoId, null, $uciId, $fecha, 'SIN_COBERTURA_DOMINGO', 'alta',
                        "La {$nombreUci} no tiene cobertura el domingo {$fecha->format('d/m/Y')}.");
                }
            }
        }

        return $count;
    }

    private function medicoTieneAusencia(int $medicoId, Carbon $fecha): bool
    {
        return Ausencia::where('medico_id', $medicoId)
            ->where('estado', 'aprobada')
            ->where('fecha_inicio', '<=', $fecha->toDateString())
            ->where('fecha_fin', '>=', $fecha->toDateString())
            ->exists();
    }

    private function crearAlerta(
        ?int    $archivoId,
        ?int    $medicoId,
        ?int    $uciId,
        mixed   $fecha,
        string  $tipo,
        string  $prioridad,
        string  $mensaje
    ): int {
        AlertaTurno::create([
            'archivo_id' => $archivoId,
            'medico_id'  => $medicoId,
            'uci_id'     => $uciId,
            'fecha'      => $fecha ? Carbon::parse($fecha)->toDateString() : null,
            'tipo'       => $tipo,
            'prioridad'  => $prioridad,
            'mensaje'    => $mensaje,
            'estado'     => 'abierta',
        ]);
        return 1;
    }
}
