<?php

namespace App\Services;

use App\Models\TurnoMedico;
use App\Models\IndicadorMedico;
use App\Models\AuditoriaSistema;
use App\Models\Ausencia;
use App\Models\TipoTurno;
use Carbon\Carbon;

class TurnoService
{
    private ExcelParserService $parser;

    /** Tabla de horas por código — fuente única de verdad */
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
    ];

    public function __construct()
    {
        $this->parser = new ExcelParserService();
    }

    /**
     * Editar un turno desde la grilla de planificación.
     * Devuelve el turno actualizado con indicadores recalculados.
     */
    public function editarTurno(
        int    $turnoId,
        string $nuevoCodigo,
        string $usuario = 'coordinador'
    ): array {
        $turno = TurnoMedico::with(['medico', 'uci'])->findOrFail($turnoId);

        if (!array_key_exists($nuevoCodigo, self::HORAS)) {
            throw new \InvalidArgumentException("Código de turno inválido: {$nuevoCodigo}");
        }

        $anterior = $turno->codigo_turno;
        $horas    = self::HORAS[$nuevoCodigo];
        $fecha    = Carbon::parse($turno->fecha);
        $esFinde  = in_array($fecha->dayOfWeek, [0, 6]);
        $esDir    = $fecha->dayOfWeek === 0;

        $anterior_snapshot = [
            'codigo_turno'    => $turno->codigo_turno,
            'horas_diurnas'   => $turno->horas_diurnas,
            'horas_nocturnas' => $turno->horas_nocturnas,
            'horas_total'     => $turno->horas_total,
        ];

        $turno->update([
            'codigo_turno'        => $nuevoCodigo,
            'horas_diurnas'       => $horas['diurnas'],
            'horas_nocturnas'     => $horas['nocturnas'],
            'horas_total'         => $horas['total'],
            'es_fin_semana'       => $esFinde,
            'es_domingo'          => $esDir,
            'editado_manualmente' => true,
            'editado_por'         => $usuario,
            'editado_at'          => now(),
        ]);

        // Recalcular indicadores del médico para ese mes/año
        $this->recalcularIndicadorMedico(
            $turno->medico_id,
            $turno->uci_id,
            $turno->archivo_id,
            $fecha->month,
            $fecha->year
        );

        AuditoriaSistema::registrar(
            accion:      'EDITAR_TURNO',
            modulo:      'planificacion',
            entidad:     'TurnoMedico',
            entidadId:   $turnoId,
            anterior:    $anterior_snapshot,
            nuevo:       ['codigo_turno' => $nuevoCodigo] + $horas,
            descripcion: "Cambio {$anterior} → {$nuevoCodigo} para {$turno->medico->nombre} el {$fecha->format('d/m/Y')}",
            usuario:     $usuario,
        );

        $turno->refresh();
        return [
            'turno'            => $turno,
            'codigo'           => $nuevoCodigo,
            'horas_diurnas'    => $horas['diurnas'],
            'horas_nocturnas'  => $horas['nocturnas'],
            'horas_total'      => $horas['total'],
        ];
    }

    /** Recalcula los indicadores de un médico en un período */
    public function recalcularIndicadorMedico(
        int $medicoId,
        int $uciId,
        int $archivoId,
        int $mes,
        int $anio
    ): void {
        $turnos      = TurnoMedico::where('medico_id', $medicoId)
                        ->where('uci_id', $uciId)
                        ->where('archivo_id', $archivoId)
                        ->get();
        $diasEnMes   = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

        $total       = 0; $diurnas = 0; $nocturnas = 0;
        $cM = $cT = $cMT = $cN = $cMTN = $cVAC = $cPER = $cINC = 0;
        $finde = $domingo = 0;

        foreach ($turnos as $t) {
            $total     += $t->horas_total;
            $diurnas   += $t->horas_diurnas;
            $nocturnas += $t->horas_nocturnas;

            match ($t->codigo_turno) {
                'M'     => $cM++,
                'T'     => $cT++,
                'MT'    => $cMT++,
                'N'     => $cN++,
                'MTN'   => ($cMTN++ && $cMT++ && $cN++),
                'MN'    => ($cM++ && $cN++),
                'VAC'   => $cVAC++,
                'PER'   => $cPER++,
                'INC'   => $cINC++,
                default => null,
            };

            if ($t->horas_total > 0 && $t->es_fin_semana) $finde++;
            if ($t->horas_total > 0 && $t->es_domingo)    $domingo++;
        }

        $semanas        = $diasEnMes / 7;
        $horasPosibles  = 24 * $diasEnMes;
        $promSemanal    = $semanas > 0    ? round($total / $semanas, 2) : 0;
        $promDiario     = $diasEnMes > 0  ? round($total / $diasEnMes, 2) : 0;
        $pctOcupacion   = $horasPosibles > 0 ? round(($total / $horasPosibles) * 100, 2) : 0;

        IndicadorMedico::updateOrCreate(
            ['medico_id' => $medicoId, 'uci_id' => $uciId, 'mes' => $mes, 'anio' => $anio],
            [
                'archivo_id'          => $archivoId,
                'total_horas'         => $total,
                'horas_diurnas'       => $diurnas,
                'horas_nocturnas'     => $nocturnas,
                'turnos_m'            => $cM,
                'turnos_t'            => $cT,
                'turnos_mt'           => $cMT,
                'turnos_n'            => $cN,
                'turnos_mtn'          => $cMTN,
                'turnos_vac'          => $cVAC,
                'turnos_per'          => $cPER,
                'turnos_inc'          => $cINC,
                'turnos_fin_semana'   => $finde,
                'turnos_domingo'      => $domingo,
                'promedio_semanal'    => $promSemanal,
                'promedio_diario'     => $promDiario,
                'porcentaje_ocupacion'=> $pctOcupacion,
            ]
        );
    }

    /**
     * Retorna la grilla mensual para un archivo y UCI.
     * Forma: [medico_id => [dia => TurnoMedico, ...], ...]
     */
    public function obtenerGrillaMes(int $archivoId, ?int $uciId = null): array
    {
        $query = TurnoMedico::with(['medico.uci'])
            ->where('archivo_id', $archivoId)
            ->orderBy('medico_id')
            ->orderBy('dia_numero');

        if ($uciId) {
            $query->where('uci_id', $uciId);
        }

        $grilla = [];
        foreach ($query->get() as $turno) {
            $grilla[$turno->medico_id][$turno->dia_numero] = $turno;
        }

        return $grilla;
    }

    /** Verifica si un médico tiene ausencia aprobada en una fecha */
    public function tieneAusencia(int $medicoId, Carbon $fecha): bool
    {
        return Ausencia::where('medico_id', $medicoId)
            ->where('estado', 'aprobada')
            ->where('fecha_inicio', '<=', $fecha->toDateString())
            ->where('fecha_fin', '>=', $fecha->toDateString())
            ->exists();
    }

    public static function horasPorCodigo(string $codigo): array
    {
        return self::HORAS[strtoupper(trim($codigo))] ?? self::HORAS['LIBRE'];
    }
}
