<?php

namespace App\Services;

use App\Models\TurnoMedico;

class ConflictoService
{
    public function validarOferta(int $medicoId, string $fecha, string $codigoTurno, ?int $uciId = null): array
    {
        // Para oferta abierta: verificar que el médico receptor no tenga ya turno ese día
        // EN ESA MISMA UCI. Trabajar en varias UCIs distintas el mismo día sí está permitido.
        $tieneConflicto = TurnoMedico::where('medico_id', $medicoId)
            ->where('fecha', $fecha)
            ->where('fue_laborado', true)
            ->whereNotIn('codigo_turno', ['','LIBRE'])
            ->when($uciId, fn($q) => $q->where('uci_id', $uciId))
            ->exists();

        return [
            'tiene_conflicto' => $tieneConflicto,
            'conflictos'      => $tieneConflicto ? ['El médico ya tiene turno asignado en esa fecha en esta UCI.'] : [],
            'advertencias'    => [],
        ];
    }
}
