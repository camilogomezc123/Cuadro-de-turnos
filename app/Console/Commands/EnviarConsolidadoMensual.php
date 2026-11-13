<?php

namespace App\Console\Commands;

use App\Http\Controllers\ConsolidadoController;
use App\Mail\ConsolidadoMensualMail;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EnviarConsolidadoMensual extends Command
{
    protected $signature = 'turnos:enviar-mensual {--mes=} {--anio=}';

    protected $description = 'Genera el Excel consolidado del mes y lo envía por correo al coordinador (archivado de fin de mes)';

    private const MESES = [
        1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio',
        7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre',
    ];

    public function handle(ConsolidadoController $controller): int
    {
        $mes  = (int) ($this->option('mes')  ?? now()->month);
        $anio = (int) ($this->option('anio') ?? now()->year);

        $destinatario = config('mail.consolidado_destinatario');
        if (!$destinatario) {
            $this->error('CONSOLIDADO_DESTINATARIO no está configurado en .env');
            return self::FAILURE;
        }

        $rutaArchivo = $controller->generarArchivoConsolidado($mes, $anio);

        try {
            Mail::to($destinatario)->send(
                new ConsolidadoMensualMail($rutaArchivo, self::MESES[$mes] ?? (string) $mes, $anio)
            );
            $this->info("Consolidado de {$mes}/{$anio} enviado a {$destinatario}.");
        } finally {
            @unlink($rutaArchivo);
        }

        return self::SUCCESS;
    }
}
