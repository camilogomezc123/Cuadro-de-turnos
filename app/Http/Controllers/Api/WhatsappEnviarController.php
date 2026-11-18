<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WhatsappEnviarController extends Controller
{
    /**
     * Permite a una automatización externa (Codex/ChatGPT) pedir el envío de un
     * WhatsApp a través de esta app, sin tener su propia sesión de WhatsApp Web.
     * Respeta WHATSAPP_ACTIVO y WHATSAPP_TEST_OVERRIDE igual que el resto de la app.
     */
    public function handle(Request $request): JsonResponse
    {
        $clave = config('services.whatsapp.inbound_key');

        if (!$clave || !hash_equals($clave, (string) $request->header('X-API-KEY'))) {
            return response()->json(['ok' => false, 'error' => 'Clave inválida.'], 401);
        }

        $datos = $request->validate([
            'telefono'    => ['required', 'string', 'max:50'],
            'mensaje'     => ['nullable', 'string', 'max:4096'],
            'imagen_url'  => ['nullable', 'url', 'max:2048'],
        ]);

        if (!empty($datos['imagen_url'])) {
            $ok = WhatsappService::enviarImagen($datos['telefono'], $datos['imagen_url'], $datos['mensaje'] ?? '');
        } elseif (!empty($datos['mensaje'])) {
            $ok = WhatsappService::enviar($datos['telefono'], $datos['mensaje']);
        } else {
            return response()->json(['ok' => false, 'error' => 'Falta mensaje o imagen_url.'], 422);
        }

        return response()->json(['ok' => $ok], $ok ? 200 : 502);
    }
}
