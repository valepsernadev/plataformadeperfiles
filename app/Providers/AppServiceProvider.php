<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
