<?php

namespace App\Mail;

use App\Models\SolicitudCambioTurno;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SolicitudCambioTurnoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SolicitudCambioTurno $solicitud) {}

    public function envelope(): Envelope
    {
        $solicitante = $this->solicitud->medicoSolicitante?->nombre ?? 'Un colega';
        $tipo = $this->solicitud->tipo_movimiento === 'donacion_directa' ? 'cedencia de turno' : 'cambio de turno';
        return new Envelope(subject: "Solicitud de {$tipo} — {$solicitante}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.solicitud-cambio-turno');
    }

    public function attachments(): array
    {
        return [];
    }
}