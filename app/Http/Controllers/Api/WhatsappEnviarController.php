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
            'telefono'       => ['required', 'string', 'max:50'],
            'mensaje'        => ['nullable', 'string', 'max:4096'],
            'imagen_url'     => ['nullable', 'url', 'max:2048'],
            // Alternativa cuando la imagen no tiene URL pública (p.ej. archivo privado de
            // Drive): el contenido de la imagen codificado en base64. Evolution API acepta
            // el campo "media" como URL o como base64, así que se envía igual.
            'imagen_base64'  => ['nullable', 'string'],
        ]);

        if (!empty($datos['imagen_url'])) {
            $ok = WhatsappService::enviarImagen($datos['telefono'], $datos['imagen_url'], $datos['mensaje'] ?? '');
        } elseif (!empty($datos['imagen_base64'])) {
            $ok = WhatsappService::enviarImagen($datos['telefono'], $datos['imagen_base64'], $datos['mensaje'] ?? '');
        } elseif (!empty($datos['mensaje'])) {
            $ok = WhatsappService::enviar($datos['telefono'], $datos['mensaje']);
        } else {
            return response()->json(['ok' => false, 'error' => 'Falta mensaje, imagen_url o imagen_base64.'], 422);
        }

        return response()->json(['ok' => $ok], $ok ? 200 : 502);
    }
}
