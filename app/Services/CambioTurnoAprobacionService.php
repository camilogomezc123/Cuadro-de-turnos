<?php

namespace App\Services;

use App\Http\Controllers\CambioTurnoController;
use App\Models\ArchivoCargado;
use App\Models\HorasAutorizacionExtra;
use App\Models\SolicitudCambioTurno;
use App\Models\TurnoMedico;
use Carbon\Carbon;

/**
 * Aplica el movimiento real de turnos de una solicitud de cambio ya aceptada
 * por el colega: mueve los turnos, valida el límite de 216h/mes y recalcula
 * el indicador de horas de los médicos involucrados.
 *
 * Usada tanto por el módulo "Cambios de turno" (CambioTurnoController) como
 * por el portal del médico (MedicoPortalController) para que, sin importar
 * desde qué pantalla el coordinador apruebe, se aplique siempre el mismo
 * resultado y las mismas reglas.
 */
class CambioTurnoAprobacionService
{
    const LIMITE_HORAS_MES = 216;

    public function __construct(private TurnoService $turnoService) {}

    /** @return array{ok: bool, error?: string} */
    public function aplicar(SolicitudCambioTurno $solicitud): array
    {
        $tOrigen = $solicitud->turnoOrigen;
        if (!$tOrigen) {
            return ['ok' => false, 'error' => 'El turno de origen ya no existe.'];
        }

        return match ($solicitud->tipo_movimiento) {
            'oferta_abierta'   => $this->aplicarReasignacionCompleta($solicitud, $tOrigen),
            'donacion_directa' => $this->aplicarDonacion($solicitud, $tOrigen),
            default            => $this->aplicarCambioDirecto($solicitud, $tOrigen),
        };
    }

    // ── Oferta abierta / donación completa: el turno pasa entero al receptor ──

    private function aplicarReasignacionCompleta(SolicitudCambioTurno $solicitud, TurnoMedico $tOrigen): array
    {
        $error = $this->chequear216h($solicitud->medico_receptor_id, $tOrigen->archivo_id, $tOrigen->horas_total ?? 0);
        if ($error) return ['ok' => false, 'error' => $error];

        $medicoOriginal = $tOrigen->medico_id;

        $tOrigen->update([
            'medico_id'          => $solicitud->medico_receptor_id,
            'estado_turno'       => 'aceptado_por_otro',
            'medico_original_id' => $medicoOriginal,
            'fue_laborado'       => true,
        ]);

        $this->recalcular($medicoOriginal, $tOrigen);
        $this->recalcular($solicitud->medico_receptor_id, $tOrigen);

        return ['ok' => true];
    }

    // ── Donación (cesión): completa o de un solo componente (M/T/N) ──────────

    private function aplicarDonacion(SolicitudCambioTurno $solicitud, TurnoMedico $tOrigen): array
    {
        if (!$solicitud->componente_turno) {
            return $this->aplicarReasignacionCompleta($solicitud, $tOrigen);
        }

        $componente = strtoupper(trim($solicitud->componente_turno));

        $tReceptor = TurnoMedico::where('medico_id', $solicitud->medico_receptor_id)
            ->where('archivo_id', $tOrigen->archivo_id)
            ->where('fecha', $tOrigen->fecha)
            ->first();

        $codigoReceptorActual = $tReceptor?->codigo_turno ?? 'LIBRE';
        $codigoReceptorNuevo  = CambioTurnoController::mergeComponente($codigoReceptorActual, $componente);
        $horasReceptor        = TurnoService::horasPorCodigo($codigoReceptorNuevo);

        $deltaReceptor = ($horasReceptor['total'] ?? 0) - ($tReceptor?->horas_total ?? 0);
        $error = $this->chequear216h($solicitud->medico_receptor_id, $tOrigen->archivo_id, $deltaReceptor);
        if ($error) return ['ok' => false, 'error' => $error];

        $codigoRestante = CambioTurnoController::restarComponente($tOrigen->codigo_turno, $componente);
        $horasRestantes = TurnoService::horasPorCodigo($codigoRestante);
        $medicoOriginal = $tOrigen->medico_id;

        $tOrigen->update([
            'codigo_turno'        => $codigoRestante,
            'horas_diurnas'       => $horasRestantes['diurnas'],
            'horas_nocturnas'     => $horasRestantes['nocturnas'],
            'horas_total'         => $horasRestantes['total'],
            'editado_manualmente' => true,
            'editado_por'         => 'coordinador',
            'editado_at'          => now(),
        ]);

        if ($tReceptor) {
            $tReceptor->update([
                'codigo_turno'    => $codigoReceptorNuevo,
                'horas_diurnas'   => $horasReceptor['diurnas'],
                'horas_nocturnas'  => $horasReceptor['nocturnas'],
                'horas_total'     => $horasReceptor['total'],
            ]);
        } else {
            $tReceptor = $this->crearTurnoPara($solicitud->medico_receptor_id, $tOrigen, $codigoReceptorNuevo, $horasReceptor);
        }

        $this->recalcular($medicoOriginal, $tOrigen);
        $this->recalcular($solicitud->medico_receptor_id, $tReceptor);

        return ['ok' => true];
    }

    // ── Cambio directo: intercambio completo o de un solo componente ────────

    private function aplicarCambioDirecto(SolicitudCambioTurno $solicitud, TurnoMedico $tOrigen): array
    {
        $tDestino = $solicitud->turnoDestino;
        if (!$tDestino) {
            return ['ok' => false, 'error' => 'No se puede aprobar: falta el turno que el receptor ofrece a cambio.'];
        }

        if ($solicitud->componente_turno) {
            $componente = strtoupper(trim($solicitud->componente_turno));

            $codigoDestinoNuevo = CambioTurnoController::mergeComponente($tDestino->codigo_turno, $componente);
            $horasDestino       = TurnoService::horasPorCodigo($codigoDestinoNuevo);
            $deltaDestino       = ($horasDestino['total'] ?? 0) - ($tDestino->horas_total ?? 0);
            $error = $this->chequear216h($tDestino->medico_id, $tDestino->archivo_id, $deltaDestino);
            if ($error) return ['ok' => false, 'error' => $error];

            $codigoOrigenNuevo = CambioTurnoController::restarComponente($tOrigen->codigo_turno, $componente);
            $horasOrigen       = TurnoService::horasPorCodigo($codigoOrigenNuevo);

            $tOrigen->update([
                'codigo_turno'    => $codigoOrigenNuevo,
                'horas_diurnas'   => $horasOrigen['diurnas'],
                'horas_nocturnas' => $horasOrigen['nocturnas'],
                'horas_total'     => $horasOrigen['total'],
                'estado_turno'    => 'reemplazado',
            ]);
            $tDestino->update([
                'codigo_turno'    => $codigoDestinoNuevo,
                'horas_diurnas'   => $horasDestino['diurnas'],
                'horas_nocturnas' => $horasDestino['nocturnas'],
                'horas_total'     => $horasDestino['total'],
                'estado_turno'    => 'reemplazado',
            ]);

            $this->recalcular($tOrigen->medico_id, $tOrigen);
            $this->recalcular($tDestino->medico_id, $tDestino);

            return ['ok' => true];
        }

        // Sin componente: los médicos se intercambian de lugar — cada uno pasa a
        // ocupar el turno (fecha, UCI y código) que tenía el otro. No se deben
        // intercambiar solo los códigos entre las mismas filas: si el turno de
        // origen y el de destino son de fechas o UCIs distintas, eso dejaría a
        // cada médico en su propia fecha/UCI original con el código del otro,
        // en vez de llevarlo realmente al turno que aceptó cambiar.
        $medicoOrigen  = $tOrigen->medico_id;
        $medicoDestino = $tDestino->medico_id;

        $deltaParaOrigen  = ($tDestino->horas_total ?? 0) - ($tOrigen->horas_total ?? 0);
        $deltaParaDestino = -$deltaParaOrigen;

        $error = $this->chequear216h($medicoDestino, $tOrigen->archivo_id, $deltaParaOrigen)
            ?? $this->chequear216h($medicoOrigen, $tDestino->archivo_id, $deltaParaDestino);
        if ($error) return ['ok' => false, 'error' => $error];

        $tOrigen->update(['medico_id' => $medicoDestino, 'estado_turno' => 'reemplazado']);
        $tDestino->update(['medico_id' => $medicoOrigen, 'estado_turno' => 'reemplazado']);

        $this->recalcular($medicoOrigen, $tOrigen);
        $this->recalcular($medicoDestino, $tOrigen);
        $this->recalcular($medicoOrigen, $tDestino);
        $this->recalcular($medicoDestino, $tDestino);

        return ['ok' => true];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function crearTurnoPara(int $medicoId, TurnoMedico $comoOrigen, string $codigo, array $horas): TurnoMedico
    {
        $fecha = Carbon::parse($comoOrigen->fecha);
        $dow   = $fecha->dayOfWeek;
        $idx   = ($dow === 0) ? 6 : $dow - 1;
        $dias  = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];

        return TurnoMedico::create([
            'archivo_id'      => $comoOrigen->archivo_id,
            'medico_id'       => $medicoId,
            'uci_id'          => $comoOrigen->uci_id,
            'fecha'           => $comoOrigen->fecha,
            'dia_numero'      => $fecha->day,
            'dia_semana'      => $dias[$idx],
            'codigo_turno'    => $codigo,
            'horas_diurnas'   => $horas['diurnas'],
            'horas_nocturnas' => $horas['nocturnas'],
            'horas_total'     => $horas['total'],
            'es_fin_semana'   => in_array($dow, [0, 6]),
            'es_domingo'      => ($dow === 0),
            'estado_turno'    => 'programado',
            'fue_laborado'    => true,
        ]);
    }

    private function recalcular(int $medicoId, TurnoMedico $turno): void
    {
        $fecha = Carbon::parse($turno->fecha);
        $this->turnoService->recalcularIndicadorMedico(
            $medicoId, $turno->uci_id, $turno->archivo_id, $fecha->month, $fecha->year
        );
    }

    private function chequear216h(int $medicoId, int $archivoId, float $deltaHoras): ?string
    {
        if ($deltaHoras <= 0) return null;

        $actuales = (float) TurnoMedico::where('medico_id', $medicoId)
            ->where('archivo_id', $archivoId)
            ->sum('horas_total');

        if (($actuales + $deltaHoras) > self::LIMITE_HORAS_MES
            && !$this->tieneAutorizacionHorasExtra($medicoId, $archivoId)) {
            return 'No se puede aprobar: el médico receptor superaría el límite de 216 h en este período. Solicita autorización de horas extra primero.';
        }

        return null;
    }

    private function tieneAutorizacionHorasExtra(int $medicoId, int $archivoId): bool
    {
        $archivo = ArchivoCargado::find($archivoId);
        if (!$archivo) return false;

        return HorasAutorizacionExtra::where('medico_id', $medicoId)
            ->where('mes', $archivo->mes)
            ->where('anio', $archivo->anio)
            ->exists();
    }
}
