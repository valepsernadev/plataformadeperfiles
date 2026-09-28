<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_de_perfil_se_renderiza(): void
    {
        $usuario = $this->crearCuentaConTarjeta();

        $this->actingAs($usuario)->get('/perfil')->assertOk();
    }

    public function test_la_pagina_de_perfil_muestra_la_tarjeta_enmascarada(): void
    {
        $usuario = $this->crearCuentaConTarjeta();

        $response = $this->actingAs($usuario)->get('/perfil');

        $response->assertSee('**** **** **** 1111');
        // Nunca el número completo.
        $response->assertDontSee('4111111111111111');
    }

    public function test_la_informacion_del_perfil_puede_actualizarse(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario)->patch('/perfil', [
            'nombre_completo' => 'Nombre Nuevo',
            'numero_identificacion' => $usuario->numero_identificacion,
            'email' => 'nuevo@example.test',
            'telefono' => '3009999999',
            'direccion' => 'Dirección Nueva 456',
            'ocupacion' => 'Arquitecta',
            'ingresos_mensuales' => '7500.50',
            'entidad_bancaria' => 'Banco Nuevo',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/perfil');

        $usuario->refresh();

        $this->assertSame('Nombre Nuevo', $usuario->nombre_completo);
        $this->assertSame('nuevo@example.test', $usuario->email);
        $this->assertSame('7500.50', $usuario->ingresos_mensuales);
        $this->assertSame('Banco Nuevo', $usuario->entidad_bancaria);
    }

    public function test_la_verificacion_de_correo_se_pierde_al_cambiar_de_correo(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->patch('/perfil', [
            'nombre_completo' => $usuario->nombre_completo,
            'numero_identificacion' => $usuario->numero_identificacion,
            'email' => 'otro@example.test',
            'telefono' => $usuario->telefono,
            'direccion' => $usuario->direccion,
            'ocupacion' => $usuario->ocupacion,
            'ingresos_mensuales' => $usuario->ingresos_mensuales,
            'entidad_bancaria' => $usuario->entidad_bancaria,
        ])->assertSessionHasNoErrors();

        $this->assertNull($usuario->refresh()->email_verified_at);
    }

    public function test_una_persona_puede_darse_de_baja(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario)->delete('/perfil', [
            'password' => 'password',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/');

        $this->assertGuest();
        // Borrado lógico: la fila sigue en la base de datos con `deleted_at`.
        $this->assertSoftDeleted('usuarios', ['id' => $usuario->id]);
        $this->assertDatabaseHas('usuarios', ['id' => $usuario->id]);
    }

    public function test_hace_falta_la_contrasena_correcta_para_darse_de_baja(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario)
            ->from('/perfil')
            ->delete('/perfil', ['password' => 'contrasena-incorrecta']);

        $response
            ->assertSessionHasErrorsIn('eliminarCuenta', 'password')
            ->assertRedirect('/perfil');

        $this->assertNotSoftDeleted('usuarios', ['id' => $usuario->id]);
    }

    public function test_la_tarjeta_puede_actualizarse_con_un_numero_valido(): void
    {
        $usuario = $this->crearCuentaConTarjeta();

        $response = $this->actingAs($usuario)->put('/perfil/tarjeta', [
            'numero_tarjeta' => '5555555555554444',
            'fecha_vencimiento' => '12/30',
            'nombre_titular' => 'Titular Distinto',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/perfil/tarjeta');

        $tarjeta = $usuario->tarjeta()->first();

        $this->assertSame('5555555555554444', $tarjeta->numero_tarjeta);
        $this->assertSame('4444', $tarjeta->ultimos_4_digitos);
        $this->assertSame('Titular Distinto', $tarjeta->nombre_titular);
        // Sigue habiendo una sola tarjeta (relación 1 a 1).
        $this->assertDatabaseCount('tarjetas', 1);
    }

    public function test_la_tarjeta_no_se_actualiza_si_el_numero_falla_luhn(): void
    {
        $usuario = $this->crearCuentaConTarjeta();

        $response = $this->actingAs($usuario)->put('/perfil/tarjeta', [
            'numero_tarjeta' => '5555555555554445',
            'fecha_vencimiento' => '12/30',
            'nombre_titular' => 'Titular Distinto',
        ]);

        $response->assertSessionHasErrors('numero_tarjeta');

        // La tarjeta anterior queda intacta.
        $this->assertSame('4111111111111111', $usuario->tarjeta()->first()->numero_tarjeta);
    }
}
