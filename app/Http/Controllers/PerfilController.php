<?php

namespace App\Http\Controllers;

use App\Http\Requests\PerfilUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Perfil del usuario autenticado.
 *
 * Ninguna de estas rutas lleva un `{id}`: el recurso es siempre
 * `$request->user()`, así que no existe una URL que permita apuntar al perfil
 * de otra persona. Es la forma más fuerte de cortar un IDOR horizontal —
 * no hay identificador que manipular. Aun así, cada acción pasa además por
 * `UserPolicy`, que vuelve a comprobar la pertenencia del recurso.
 */
class PerfilController extends Controller
{
    /**
     * Ver el perfil propio.
     */
    public function edit(Request $request): View
    {
        $usuario = $request->user();

        Gate::authorize('view', $usuario);

        return view('perfil.edit', [
            'usuario' => $usuario,
            'tarjeta' => $usuario->tarjeta,
        ]);
    }

    /**
     * Actualizar la información personal y financiera.
     */
    public function update(PerfilUpdateRequest $request): RedirectResponse
    {
        $usuario = $request->user();

        Gate::authorize('update', $usuario);

        // Solo los campos validados. `role` no está entre ellos, así que un
        // PATCH con `role=administrador` no escala privilegios.
        $usuario->fill($request->validated());

        if ($usuario->isDirty('email')) {
            // Al cambiar el correo, la verificación previa deja de ser válida.
            $usuario->email_verified_at = null;
        }

        $usuario->save();

        return redirect()
            ->route('perfil.edit')
            ->with('estado', 'perfil-actualizado');
    }

    /**
     * Baja voluntaria de la propia cuenta (soft delete, igual que en el panel
     * admin: la fila se conserva con `deleted_at`).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('eliminarCuenta', [
            'password' => ['required', 'current_password'],
        ]);

        $usuario = $request->user();

        Gate::authorize('delete', $usuario);

        Auth::logout();

        $usuario->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
