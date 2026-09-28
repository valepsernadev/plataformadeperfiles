<?php

use App\Http\Controllers\Admin\UsuarioController as AdminUsuarioController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\TarjetaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Perfil y tarjeta del usuario autenticado
|--------------------------------------------------------------------------
|
| Ninguna ruta lleva `{id}`. El recurso es siempre `$request->user()`, así que
| no existe una URL que permita apuntar al perfil de otra persona. Los
| controladores, además, revalidan la pertenencia con `UserPolicy`.
|
| `no-admin` deja estas rutas fuera del alcance del rol administrador: su panel
| sirve solo para administrar usuarios y no tiene perfil propio.
|
*/
Route::middleware(['auth', 'no-admin'])->group(function () {
    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::patch('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::delete('/perfil', [PerfilController::class, 'destroy'])->name('perfil.destroy');

    // La tarjeta vive en su propia tabla y en su propio endpoint, porque es el
    // único dato clasificado como Restringido.
    Route::get('/perfil/tarjeta', [TarjetaController::class, 'edit'])->name('perfil.tarjeta.edit');
    Route::put('/perfil/tarjeta', [TarjetaController::class, 'update'])->name('perfil.tarjeta.update');
});

/*
|--------------------------------------------------------------------------
| Panel de administración
|--------------------------------------------------------------------------
|
| El middleware `admin` (EnsureUserIsAdmin) rechaza en el backend a cualquier
| cuenta que no tenga el rol. Ocultar el enlace en la navegación es solo
| cosmético: la barrera real está aquí y en `UserPolicy`.
|
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/usuarios', [AdminUsuarioController::class, 'index'])->name('usuarios.index');

    // `withTrashed()` permite que la ruta resuelva también cuentas con borrado
    // lógico, para que el controlador pueda rechazarlas explícitamente en vez
    // de dar un 404.
    Route::delete('/usuarios/{usuario}', [AdminUsuarioController::class, 'destroy'])
        ->name('usuarios.destroy')
        ->withTrashed();
});

require __DIR__.'/auth.php';
