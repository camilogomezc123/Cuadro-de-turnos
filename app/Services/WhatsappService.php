<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    /**
     * Envía un mensaje de WhatsApp a través del microservicio local (whatsapp-web.js).
     * No lanza excepciones: si el servicio está apagado, desconectado o falla, solo
     * registra el error y retorna false, para no romper el flujo principal de la app.
     */
    public static function enviar(?string $telefono, string $mensaje): bool
    {
        if (!config('services.whatsapp.activo') || !$telefono) {
            return false;
        }

        $url = rtrim((string) config('services.whatsapp.url'), '/') . '/enviar';

        try {
            $respuesta = Http::withHeaders(['X-API-KEY' => config('services.whatsapp.api_key')])
                ->timeout(10)
                ->post($url, [
                    'telefono' => $telefono,
                    'mensaje'  => $mensaje,
                ]);

            if (!$respuesta->successful()) {
                Log::warning('WhatsApp: el microservicio respondió con error', [
                    'telefono' => $telefono,
                    'status'   => $respuesta->status(),
                    'body'     => $respuesta->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp: no se pudo contactar el microservicio', [
                'telefono' => $telefono,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }
}
