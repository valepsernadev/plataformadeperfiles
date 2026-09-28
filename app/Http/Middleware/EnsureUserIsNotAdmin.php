<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El panel de administración sirve solo para administrar usuarios: quien tiene
 * ese rol no tiene perfil propio.
 *
 * Un administrador que intente entrar a las rutas de perfil (ver o editar sus
 * datos personales, sus datos financieros o su tarjeta) es devuelto al panel.
 */
class EnsureUserIsNotAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario !== null && $usuario->esAdministrador()) {
            return redirect()->route('admin.usuarios.index');
        }

        return $next($request);
    }
}
