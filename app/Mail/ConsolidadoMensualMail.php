<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConsolidadoMensualMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $rutaArchivo,
        public string $nombreMes,
        public int $anio,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Consolidado de turnos UCI — {$this->nombreMes} {$this->anio}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.consolidado-mensual', with: [
            'nombreMes' => $this->nombreMes,
            'anio'      => $this->anio,
        ]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->rutaArchivo)
                ->as("Consolidado_UCI_{$this->nombreMes}_{$this->anio}.xlsx")
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
