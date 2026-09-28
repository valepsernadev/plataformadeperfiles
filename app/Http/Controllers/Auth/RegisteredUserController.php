<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * El usuario y su tarjeta se crean dentro de una transacción: si la tarjeta
     * falla, no queda una cuenta a medias sin tarjeta.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        // `validated()` devuelve SOLO los campos con regla. Cualquier cosa que
        // el cliente invente (`role`, `cvv`, `usuario_id`, `id`...) se descarta
        // aquí, antes de llegar al modelo.
        $datos = $request->validated();

        $usuario = DB::transaction(function () use ($datos): User {
            $usuario = User::create([
                'nombre_completo' => $datos['nombre_completo'],
                'numero_identificacion' => $datos['numero_identificacion'],
                'email' => $datos['email'],
                // bcrypt, irreversible (guía, sección 7). El cast `hashed` del
                // modelo no vuelve a hashear un valor que ya lo está.
                'password' => Hash::make($datos['password']),
                'telefono' => $datos['telefono'],
                'direccion' => $datos['direccion'],
                'ocupacion' => $datos['ocupacion'],
                'ingresos_mensuales' => $datos['ingresos_mensuales'],
                'entidad_bancaria' => $datos['entidad_bancaria'],
                // `role` NO se escribe aquí a propósito: toda cuenta nueva nace
                // como `usuario` por el valor por defecto de la columna.
            ]);

            // La tarjeta se crea a través de la relación, que fija `usuario_id`
            // directamente. El número se cifra solo, por el cast `encrypted`.
            $usuario->tarjeta()->create([
                'numero_tarjeta' => $datos['numero_tarjeta'],
                'ultimos_4_digitos' => substr($datos['numero_tarjeta'], -4),
                'nombre_titular' => $datos['nombre_titular'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'],
            ]);

            return $usuario;
        });

        event(new Registered($usuario));

        Auth::login($usuario);

        return redirect(route('dashboard', absolute: false));
    }
}
