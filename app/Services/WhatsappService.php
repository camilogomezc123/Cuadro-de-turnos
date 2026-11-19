<?php

namespace App\Services;

use App\Models\Medico;
use Illuminate\Support\Facades\Cache;
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
        if (!self::respetarLimiteEnvio()) {
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

    /**
     * Antiabuso: evita que WhatsApp marque el número por enviar demasiados
     * mensajes o muy pegados entre sí. Espera (sleep) si hace falta separación,
     * y bloquea el envío por completo si ya se alcanzó el tope diario.
     * Devuelve false si el envío debe cancelarse por tope diario alcanzado.
     */
    private static function respetarLimiteEnvio(): bool
    {
        $hoy = now()->format('Y-m-d');
        $claveContador = "whatsapp:enviados:{$hoy}";
        $limiteDiario = (int) config('services.whatsapp.limite_diario', 150);

        $enviadosHoy = (int) Cache::get($claveContador, 0);
        if ($enviadosHoy >= $limiteDiario) {
            Log::warning('WhatsApp: tope diario de mensajes alcanzado, envío cancelado', [
                'enviados_hoy' => $enviadosHoy,
                'limite_diario' => $limiteDiario,
            ]);
            return false;
        }

        $intervaloMinimo = (int) config('services.whatsapp.intervalo_minimo_segundos', 20);
        $claveUltimoEnvio = 'whatsapp:ultimo_envio_en';
        $ultimoEnvio = Cache::get($claveUltimoEnvio);

        if ($ultimoEnvio) {
            $segundosDesdeUltimo = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($ultimoEnvio));
            if ($segundosDesdeUltimo < $intervaloMinimo) {
                // Tope de espera de 5s: un sleep() largo aquí bloquea la petición HTTP
                // entrante y puede chocar con el timeout de nginx/php-fpm (ya pasó: un
                // envío legítimo se perdió por esto). Mejor ceder algo de espaciado que
                // arriesgar la entrega.
                sleep(min($intervaloMinimo - $segundosDesdeUltimo, 5));
            }
        }

        Cache::put($claveUltimoEnvio, now()->toDateTimeString(), now()->addMinutes(10));
        Cache::put($claveContador, $enviadosHoy + 1, now()->endOfDay());

        return true;
    }

    private static function enviarViaEvolution(string $telefono, string $mensaje): bool
    {
        if (!self::respetarLimiteEnvio()) {
            return false;
        }

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

    /**
     * Envía una imagen (URL pública) con pie de foto opcional. Mismas reglas de
     * activo/override que enviar(); solo soporta el driver 'evolution'.
     */
    public static function enviarImagen(?string $telefono, string $urlImagen, string $caption = '', bool $ignorarOverride = false): bool
    {
        if (!config('services.whatsapp.activo') || !$telefono) {
            return false;
        }

        if (!$ignorarOverride && ($override = config('services.whatsapp.test_override'))) {
            $caption = "[a {$telefono}] {$caption}";
            $telefono = $override;
        }

        if (config('services.whatsapp.driver', 'local') !== 'evolution') {
            Log::warning('WhatsApp: enviarImagen solo soporta el driver evolution');
            return false;
        }

        if (!self::respetarLimiteEnvio()) {
            return false;
        }

        $instancia = config('services.evolution.instance');
        $url = rtrim((string) config('services.evolution.url'), '/') . "/message/sendMedia/{$instancia}";

        try {
            $respuesta = Http::withHeaders(['apikey' => config('services.evolution.token')])
                ->timeout(20)
                ->post($url, [
                    'number'    => self::normalizarNumero($telefono),
                    'mediatype' => 'image',
                    'media'     => $urlImagen,
                    'caption'   => $caption,
                ]);

            if (!$respuesta->successful()) {
                Log::warning('WhatsApp (evolution): error enviando imagen', [
                    'telefono' => $telefono,
                    'status'   => $respuesta->status(),
                    'body'     => $respuesta->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp (evolution): no se pudo enviar la imagen', [
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
