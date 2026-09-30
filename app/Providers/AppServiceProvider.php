<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurarLimiteDeLogin();
        $this->configurarPoliticaDeContrasenas();
    }

    /**
     * Política de contraseñas de toda la aplicación (guía, sección 7).
     *
     * Se declara UNA sola vez aquí y no en cada formulario, a propósito. Los
     * tres sitios donde se escribe una contraseña ya llaman a
     * `Password::defaults()`, así que todos heredan esta política sin tocarlos:
     *
     *  - registro            -> app/Http/Requests/Auth/RegisterRequest.php
     *  - cambio desde perfil -> app/Http/Controllers/Auth/PasswordController.php
     *  - restablecer por correo -> app/Http/Controllers/Auth/NewPasswordController.php
     *
     * Definirla aquí y no repetirla en cada uno es lo que impide el fallo
     * clásico de endurecer el registro y dejar abierta la puerta de atrás: un
     * usuario no puede registrarse con una contraseña fuerte y acto seguido
     * cambiarla por `12345678` desde su perfil.
     *
     * La política: mínimo 8 caracteres, con al menos una letra, un número y un
     * símbolo. `letters()` no estaba en el pedido original, pero sin ella una
     * contraseña como `1234567!` pasaría el filtro; añadirla es una palabra y
     * cierra ese hueco.
     *
     * Los mensajes en español ya existen en lang/es/validation.php
     * (claves `password.letters`, `password.numbers` y `password.symbols`).
     *
     * NOTA: `uncompromised()` —la comprobación contra filtraciones conocidas—
     * queda deliberadamente fuera. Requiere llamar a la API de Have I Been
     * Pwned, lo que añade una dependencia de red al registro y al cambio de
     * contraseña. Es una mejora razonable para producción.
     */
    private function configurarPoliticaDeContrasenas(): void
    {
        Password::defaults(fn (): Password => Password::min(8)
            ->letters()
            ->numbers()
            ->symbols());
    }

    /**
     * Rate limiting del endpoint de login (guía, secciones 5 y 7).
     *
     * Hay dos límites complementarios y atacan amenazas distintas:
     *
     * 1. El de Breeze (`LoginRequest::ensureIsNotRateLimited`), con clave
     *    `email|ip`, corta la **fuerza bruta** contra una cuenta concreta.
     *
     * 2. Este, con clave solo por IP, corta el **credential stuffing**: probar
     *    un diccionario de contraseñas contra muchísimos correos distintos
     *    desde la misma máquina. El límite por `email|ip` no lo ve, porque cada
     *    correo abre su propio contador.
     *
     * El umbral es holgado (20 por minuto) para no estorbar a una persona real
     * escribiendo mal su contraseña, pero frena cualquier barrido automatizado.
     *
     * Al superarlo, el middleware `throttle` responde 429 y se renderiza
     * resources/views/errors/429.blade.php.
     */
    private function configurarLimiteDeLogin(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });
    }
}
