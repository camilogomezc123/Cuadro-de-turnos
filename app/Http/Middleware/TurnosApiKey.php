<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TurnosApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $path = config('turnos_api.key_file');
        $keys = [];
        if (is_file($path)) {
            $handle = fopen($path, 'r');
            if ($handle) {
                if (flock($handle, LOCK_SH)) {
                    $keys = json_decode(stream_get_contents($handle), true);
                    flock($handle, LOCK_UN);
                }
                fclose($handle);
            }
        }
        if (!is_array($keys) || !$keys) {
            return response()->json(['message' => 'API no habilitada.'], 503);
        }

        $token = $request->bearerToken();
        if (!is_string($token) || strlen($token) > 200) {
            return response()->json(['message' => 'Clave de acceso requerida.'], 401);
        }
        $hash = hash('sha256', $token);
        foreach ($keys as $stored) {
            if (is_string($stored) && hash_equals($stored, $hash)) {
                return $next($request)->header('Cache-Control', 'private, no-store');
            }
        }

        return response()->json(['message' => 'Clave de acceso inválida.'], 401);
    }
}
