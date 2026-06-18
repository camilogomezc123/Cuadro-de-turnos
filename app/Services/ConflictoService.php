<?php

namespace App\Services;

use App\Models\TurnoMedico;
use App\Models\Medico;
use Carbon\Carbon;

class ConflictoService
{
    const LIMITE_HORAS_HABIL   = 12;
    const LIMITE_HORAS_MES     = 200;
    const CODIGOS_NO_EN_HABIL  = ['MTN'];

    public function validarAsignacion(
        int    $medicoId,
        string $fecha,
        string $codigoNuevo,
        ?int   $turnoExcluidoId = null,
        bool   $esMaestro       = false
    ): array {
        $fechaCarbon = Carbon::parse($fecha);
        $conflictos  = [];
        $advertencias= [];

        // 1. Médico activo
        $medico = Medico::find($medicoId);
        if (!$medico || !$medico->activo) {
            $conflictos[] = 'El médico no está activo en el sistema.';
        }

        $horasNuevas = TurnoMedico::horasPorCodigo($codigoNuevo);
        $esDiaHabil  = !in_array($fechaCarbon->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);

        // 2. MTN en día hábil
        if (in_array(strtoupper($codigoNuevo), self::CODIGOS_NO_EN_HABIL) && $esDiaHabil) {
            if ($esMaestro) {
                $advertencias[] = 'MTN asignado en día hábil (lunes-viernes). Solo permitido en fines de semana.';
            } else {
                $conflictos[] = 'MTN no está permitido en días hábiles (lunes-viernes).';
            }
        }

        // 3. Horas del día (sumando otras UCIs)
        $turnosExistentes = TurnoMedico::where('medico_id', $medicoId)
            ->where('fecha', $fecha)
            ->when($turnoExcluidoId, fn($q) => $q->where('id', '!=', $turnoExcluidoId))
            ->where('fue_laborado', true)
            ->whereNotIn('codigo_turno', ['','LIBRE','VAC','PER','INC'])
            ->get();

        $horasYaTieneHoy = $turnosExistentes->sum(fn($t) => $t->horas_reconocidas ?? $t->horas_total ?? 0);
        $totalHorasHoy   = $horasYaTieneHoy + $horasNuevas;

        if ($esDiaHabil && $totalHorasHoy > self::LIMITE_HORAS_HABIL) {
            $msg = "El médico quedaría con {$totalHorasHoy}h el {$fecha} (límite: 12h en días hábiles).";
            if ($esMaestro) {
                $advertencias[] = $msg;
            } else {
                $conflictos[] = $msg;
            }
        }

        if ($turnosExistentes->isNotEmpty()) {
            $uciActual = $turnosExistentes->pluck('uci_id')->unique()->join(', ');
            $advertencias[] = "El médico ya tiene turno ese día en UCI(s): {$uciActual}.";
        }

        // 4. Horas del mes (multi-UCI)
        $mes  = $fechaCarbon->month;
        $anio = $fechaCarbon->year;

        $horasMes = TurnoMedico::where('medico_id', $medicoId)
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->when($turnoExcluidoId, fn($q) => $q->where('id', '!=', $turnoExcluidoId))
            ->where('fue_laborado', true)
            ->sum(\DB::raw('COALESCE(horas_reconocidas, horas_total)'));

        $totalHorasMes = $horasMes + $horasNuevas;
        if ($totalHorasMes > self::LIMITE_HORAS_MES) {
            $msg = "El médico superaría las 200h mensuales ({$totalHorasMes}h).";
            if ($esMaestro) {
                $advertencias[] = $msg;
            } else {
                $conflictos[] = $msg;
            }
        }

        return [
            'tiene_conflicto' => count($conflictos) > 0,
            'conflictos'      => $conflictos,
            'advertencias'    => $advertencias,
            'horas_dia'       => $totalHorasHoy,
            'horas_mes'       => $totalHorasMes,
        ];
    }

    public function validarOferta(int $medicoId, string $fecha, string $codigoTurno): array
    {
        // Para oferta abierta: verificar que el médico receptor no tiene turno ese día
        $tieneConflicto = TurnoMedico::where('medico_id', $medicoId)
            ->where('fecha', $fecha)
            ->where('fue_laborado', true)
            ->whereNotIn('codigo_turno', ['','LIBRE'])
            ->exists();

        return [
            'tiene_conflicto' => $tieneConflicto,
            'conflictos'      => $tieneConflicto ? ['El médico ya tiene turno asignado en esa fecha.'] : [],
            'advertencias'    => [],
        ];
    }
}
