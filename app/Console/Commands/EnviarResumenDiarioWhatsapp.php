<?php

namespace App\Console\Commands;

use App\Models\TurnoMedico;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EnviarResumenDiarioWhatsapp extends Command
{
    protected $signature = 'turnos:resumen-diario-whatsapp {--dry-run : Solo muestra el mensaje, no lo envía}';

    protected $description = 'Envía por WhatsApp quién está de turno hoy, agrupado por UCI';

    public function handle(): int
    {
        $hoy = Carbon::today();

        $turnos = TurnoMedico::with(['medico', 'uci'])
            ->whereDate('fecha', $hoy)
            ->where('fue_laborado', true)
            ->whereNotIn('estado_turno', ['no_asistido', 'cancelado', 'descubierto'])
            ->whereIn('codigo_turno', ['M', 'T', 'MT', 'N', 'TN', 'MTN', 'MN'])
            ->get()
            ->filter(fn (TurnoMedico $t) => $t->medico && $t->uci)
            ->groupBy(fn (TurnoMedico $t) => $t->uci->nombre)
            ->sortKeys();

        if ($turnos->isEmpty()) {
            $this->info('No hay turnos registrados para hoy, no se envía resumen.');
            return self::SUCCESS;
        }

        $lineas = ["📋 Turnos de hoy " . $hoy->locale('es')->translatedFormat('l d \d\e F') . ":", ''];

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
        $this->info($ok ? 'Resumen diario enviado.' : 'No se pudo enviar el resumen diario (revisa los logs).');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
