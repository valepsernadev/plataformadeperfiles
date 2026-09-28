<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsNotAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Control de acceso por rol: `->middleware('admin')` en las rutas del
        // panel. Ver app/Http/Middleware/EnsureUserIsAdmin.php.
        //
        // `no-admin` es la cara opuesta: el administrador no tiene perfil, así
        // que las rutas de perfil lo devuelven al panel.
        // Ver app/Http/Middleware/EnsureUserIsNotAdmin.php.
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'no-admin' => EnsureUserIsNotAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
