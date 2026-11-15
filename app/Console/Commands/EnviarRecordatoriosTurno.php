<?php

namespace App\Console\Commands;

use App\Models\TurnoMedico;
use App\Notifications\RecordatorioTurno;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EnviarRecordatoriosTurno extends Command
{
    /**
     * @var string
     */
    protected $signature = 'turnos:recordatorios {--horas=12 : Horas de anticipación antes del inicio del turno}';

    /**
     * @var string
     */
    protected $description = 'Envía por correo el recordatorio de turno a los médicos cuyo turno inicia dentro de N horas';

    public function handle(): int
    {
        $horasAnticipacion = (int) $this->option('horas');
        $ahora             = Carbon::now();
        $limite             = $ahora->copy()->addHours($horasAnticipacion);

        $candidatos = TurnoMedico::with(['medico', 'uci'])
            ->whereNull('recordatorio_enviado_at')
            ->whereBetween('fecha', [$ahora->copy()->toDateString(), $limite->copy()->toDateString()])
            ->whereIn('codigo_turno', array_keys(TurnoMedico::HORA_INICIO))
            ->whereNotIn('estado_turno', ['cancelado', 'no_asistido', 'descubierto'])
            ->get();

        $enviados = 0;

        foreach ($candidatos as $turno) {
            $inicio = $turno->inicio_turno;

            if (!$inicio || $inicio->lessThanOrEqualTo($ahora) || $inicio->greaterThan($limite)) {
                continue;
            }

            $medico = $turno->medico;

            if (!$medico || !$medico->email) {
                continue;
            }

            $medico->notify(new RecordatorioTurno($turno));
            $turno->update(['recordatorio_enviado_at' => $ahora]);
            $enviados++;
        }

        $this->info("Recordatorios enviados: {$enviados}");

        return self::SUCCESS;
    }
}
