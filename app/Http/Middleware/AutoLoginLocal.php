<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solo en entorno local: si nadie ha iniciado sesión, entra automáticamente
 * como el usuario master para no tener que loguearse en cada prueba.
 * No tiene ningún efecto fuera de APP_ENV=local (producción no se ve afectada).
 */
class AutoLoginLocal
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local') && !Auth::check()) {
            $master = User::where('rol', 'master')->first();
            if ($master) {
                Auth::login($master);
            }
        }

        return $next($request);
    }
}
