<?php

namespace App\Http\Controllers;

use App\Http\Requests\TarjetaUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tarjeta del usuario autenticado — el dato clasificado como **Restringido**.
 *
 * Igual que en el perfil, no hay `{id}` en la ruta: la tarjeta se resuelve
 * siempre con `$request->user()->tarjeta()`. Enviar `usuario_id` o
 * `tarjeta_id` en el cuerpo no tiene efecto, porque esas claves no están en
 * las reglas de validación ni en `Tarjeta::$fillable`.
 */
class TarjetaController extends Controller
{
    public function edit(Request $request): View
    {
        return view('perfil.tarjeta', [
            'tarjeta' => $request->user()->tarjeta,
        ]);
    }

    public function update(TarjetaUpdateRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        // `updateOrCreate` sobre la relación: la clave foránea la pone Eloquent,
        // nunca el cliente.
        $request->user()->tarjeta()->updateOrCreate([], [
            // Se guarda cifrado por el cast `encrypted` del modelo.
            'numero_tarjeta' => $datos['numero_tarjeta'],
            // En claro, para poder mostrar **** **** **** 1234 sin descifrar.
            'ultimos_4_digitos' => substr($datos['numero_tarjeta'], -4),
            'nombre_titular' => $datos['nombre_titular'],
            'fecha_vencimiento' => $datos['fecha_vencimiento'],
        ]);

        return redirect()
            ->route('perfil.tarjeta.edit')
            ->with('estado', 'tarjeta-actualizada');
    }
}
