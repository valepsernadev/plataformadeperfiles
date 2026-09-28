<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Control de acceso por rol (guía, secciones 5 y 7).
 *
 * Se aplica en el backend, no ocultando el enlace en la vista: "ocultar un
 * botón no es seguridad". Toda ruta bajo /admin pasa por aquí, así que un
 * usuario con rol `usuario` recibe 403 aunque conozca la URL y aunque
 * manipule el formulario o las cabeceras.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario === null || ! $usuario->esAdministrador()) {
            abort(403);
        }

        return $next($request);
    }
}
