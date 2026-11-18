<?php

namespace App\Services;

use App\Models\Medico;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    /**
     * Envía un mensaje de WhatsApp a un médico, respetando su consentimiento
     * (whatsapp_notificaciones) y si tiene teléfono registrado. Úsese para
     * notificaciones dirigidas a una persona (cambios de turno, recordatorios).
     */
    public static function notificarMedico(?Medico $medico, string $mensaje): bool
    {
        if (!$medico || !$medico->telefono || !$medico->whatsapp_notificaciones) {
            return false;
        }

        return self::enviar($medico->telefono, $mensaje);
    }

    /**
     * Envía un mensaje de WhatsApp. Soporta dos backends según WHATSAPP_DRIVER:
     * 'local' (microservicio whatsapp-web.js) o 'evolution' (Evolution API).
     * No lanza excepciones: si está apagado, desconectado o falla, solo registra
     * el error y retorna false, para no romper el flujo principal de la app.
     *
     * $ignorarOverride: solo para envíos deliberados a un grupo/difusión ya
     * confirmados (p.ej. el resumen diario al grupo de la UCI); los envíos a
     * personas individuales deben dejarlo en false para respetar el modo de
     * prueba (WHATSAPP_TEST_OVERRIDE) mientras se valida el sistema.
     */
    public static function enviar(?string $telefono, string $mensaje, bool $ignorarOverride = false): bool
    {
        if (!config('services.whatsapp.activo') || !$telefono) {
            return false;
        }

        if (!$ignorarOverride && ($override = config('services.whatsapp.test_override'))) {
            $mensaje = "[a {$telefono}] {$mensaje}";
            $telefono = $override;
        }

        return match (config('services.whatsapp.driver', 'local')) {
            'evolution' => self::enviarViaEvolution($telefono, $mensaje),
            default => self::enviarViaLocal($telefono, $mensaje),
        };
    }

    private static function enviarViaLocal(string $telefono, string $mensaje): bool
    {
        $url = rtrim((string) config('services.whatsapp.url'), '/') . '/enviar';

        try {
            $respuesta = Http::withHeaders(['X-API-KEY' => config('services.whatsapp.api_key')])
                ->timeout(10)
                ->post($url, [
                    'telefono' => $telefono,
                    'mensaje'  => $mensaje,
                ]);

            if (!$respuesta->successful()) {
                Log::warning('WhatsApp (local): el microservicio respondió con error', [
                    'telefono' => $telefono,
                    'status'   => $respuesta->status(),
                    'body'     => $respuesta->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp (local): no se pudo contactar el microservicio', [
                'telefono' => $telefono,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    private static function enviarViaEvolution(string $telefono, string $mensaje): bool
    {
        $instancia = config('services.evolution.instance');
        $url = rtrim((string) config('services.evolution.url'), '/') . "/message/sendText/{$instancia}";

        try {
            $respuesta = Http::withHeaders(['apikey' => config('services.evolution.token')])
                ->timeout(10)
                ->post($url, [
                    'number' => self::normalizarNumero($telefono),
                    'text'   => $mensaje,
                ]);

            if (!$respuesta->successful()) {
                Log::warning('WhatsApp (evolution): la API respondió con error', [
                    'telefono' => $telefono,
                    'status'   => $respuesta->status(),
                    'body'     => $respuesta->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp (evolution): no se pudo contactar la API', [
                'telefono' => $telefono,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    private static function normalizarNumero(string $telefono): string
    {
        // JID ya formado (número o grupo, p.ej. ...@s.whatsapp.net o ...@g.us): no tocar.
        if (str_contains($telefono, '@')) {
            return $telefono;
        }

        $numero = preg_replace('/\D/', '', $telefono) ?? '';

        if (strlen($numero) <= 10) {
            $numero = '57' . $numero;
        }

        return $numero;
    }
}
