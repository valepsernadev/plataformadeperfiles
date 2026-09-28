<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Manejo de errores genérico (guía, secciones 4, 5 y 7).
 *
 * El riesgo es la fuga de información: un stack trace revela la versión de
 * Laravel, la ruta de los archivos, la estructura de la base de datos y a
 * veces fragmentos de las consultas. Con APP_DEBUG=false el usuario solo ve
 * una página genérica; el detalle queda en storage/logs.
 */
class ErroresGenericosTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_aplicacion_no_expone_el_modo_debug(): void
    {
        $this->assertFalse(config('app.debug'));
    }

    public function test_una_ruta_inexistente_muestra_la_pagina_propia(): void
    {
        $response = $this->get('/una-ruta-que-no-existe');

        $response->assertNotFound();
        $response->assertSee('Página no encontrada');
    }

    public function test_la_pagina_404_no_revela_el_framework(): void
    {
        $response = $this->get('/una-ruta-que-no-existe');

        $response->assertDontSee('Laravel');
        $response->assertDontSee('vendor/');
    }

    public function test_un_error_interno_no_filtra_la_traza_ni_los_detalles(): void
    {
        Route::middleware('web')->get('/_prueba-de-error', function () {
            throw new RuntimeException('detalle-interno-secreto-que-no-debe-salir');
        });

        $response = $this->get('/_prueba-de-error');

        $response->assertStatus(500);

        // Nada de esto puede llegar al usuario.
        $response->assertDontSee('detalle-interno-secreto-que-no-debe-salir');
        $response->assertDontSee('RuntimeException');
        $response->assertDontSee('vendor/laravel');
        $response->assertDontSee('stack');
        $response->assertDontSee('.php');

        // En su lugar, un mensaje genérico.
        $response->assertSee('Ocurrió un error inesperado');
    }

    public function test_el_acceso_denegado_usa_la_pagina_403_propia(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario)->get('/admin/usuarios');

        $response->assertForbidden();
        $response->assertSee('Acceso denegado');

        // Y sin filtrar por qué se denegó ni qué hay detrás.
        $response->assertDontSee('Laravel');
        $response->assertDontSee('vendor/');
    }
}
