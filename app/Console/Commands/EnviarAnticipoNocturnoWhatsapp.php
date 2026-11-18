<?php

namespace App\Console\Commands;

use App\Models\TurnoMedico;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EnviarAnticipoNocturnoWhatsapp extends Command
{
    protected $signature = 'turnos:anticipo-nocturno-whatsapp {--dry-run : Solo muestra el mensaje, no lo envía}';

    protected $description = 'Envía por WhatsApp el anticipo del turno nocturno de hoy (19:00 a mañana 07:00), agrupado por UCI';

    // Códigos cuyo turno cubre la noche (todos terminan en N: noche incluida).
    private const CODIGOS_NOCHE = ['N', 'TN', 'MTN', 'MN'];

    public function handle(): int
    {
        $hoy = Carbon::today();

        $turnos = TurnoMedico::with(['medico', 'uci'])
            ->whereDate('fecha', $hoy)
            ->where('fue_laborado', true)
            ->whereNotIn('estado_turno', ['no_asistido', 'cancelado', 'descubierto'])
            ->whereIn('codigo_turno', self::CODIGOS_NOCHE)
            ->get()
            ->filter(fn (TurnoMedico $t) => $t->medico && $t->uci)
            ->groupBy(fn (TurnoMedico $t) => $t->uci->nombre)
            ->sortKeys();

        if ($turnos->isEmpty()) {
            $this->info('No hay turno nocturno registrado para hoy, no se envía anticipo.');
            return self::SUCCESS;
        }

        $lineas = [
            "🌙 Anticipo turno nocturno de hoy " . $hoy->locale('es')->translatedFormat('l d \d\e F')
                . " (19:00 a mañana 07:00):",
            '',
        ];

        foreach ($turnos as $ucaNombre => $delUci) {
            $lineas[] = "*{$ucaNombre}*";
            foreach ($delUci->sortBy(fn (TurnoMedico $t) => $t->medico->nombre_completo) as $t) {
                $lineas[] = "- {$t->medico->nombre_completo} ({$t->codigo_turno})";
            }
            $lineas[] = '';
        }

        $mensaje = trim(implode("\n", $lineas));

        if ($this->option('dry-run')) {
            $this->line($mensaje);
            return self::SUCCESS;
        }

        $destino = config('services.whatsapp.resumen_destino');

        if (!$destino) {
            $this->error('Falta configurar WHATSAPP_RESUMEN_DESTINO.');
            return self::FAILURE;
        }

        $ok = WhatsappService::enviar($destino, $mensaje, ignorarOverride: true);
        $this->info($ok ? 'Anticipo nocturno enviado.' : 'No se pudo enviar el anticipo nocturno (revisa los logs).');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
