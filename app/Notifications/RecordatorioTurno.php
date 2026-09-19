<?php

namespace App\Notifications;

use App\Models\TurnoMedico;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecordatorioTurno extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TurnoMedico $turno)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inicio = $this->turno->inicio_turno;
        $uci    = $this->turno->uci->nombre ?? 'tu UCI';

        return (new MailMessage)
            ->subject('Recordatorio de turno — '.$inicio?->translatedFormat('l d/m/Y').' '.$inicio?->format('H:i'))
            ->greeting('Hola '.$notifiable->nombre.',')
            ->line('Este es un recordatorio de tu próximo turno:')
            ->line('**UCI:** '.$uci)
            ->line('**Turno:** '.$this->turno->codigo_turno)
            ->line('**Inicio:** '.$inicio?->translatedFormat('l d/m/Y \a \l\a\s H:i'))
            ->line('Si tienes algún inconveniente para asistir, gestiona un cambio de turno desde el sistema con anticipación.');
    }
}
