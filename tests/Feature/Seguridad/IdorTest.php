<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * IDOR — Insecure Direct Object Reference (guía, secciones 4 y 5).
 *
 * La amenaza es la escalada horizontal: una cuenta accediendo al perfil o a la
 * tarjeta de otra. Aquí se comprueban las dos capas:
 *
 * 1. Estructural: no existe ninguna ruta de perfil con `{id}`, así que no hay
 *    identificador que manipular.
 * 2. Explícita: `UserPolicy` compara el dueño del recurso con quien lo pide.
 */
class IdorTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_policy_solo_deja_a_cada_quien_con_su_propio_recurso(): void
    {
        $ana = User::factory()->create();
        $luis = User::factory()->create();

        $this->assertTrue(Gate::forUser($ana)->allows('view', $ana));
        $this->assertTrue(Gate::forUser($ana)->allows('update', $ana));

        $this->assertFalse(Gate::forUser($ana)->allows('view', $luis));
        $this->assertFalse(Gate::forUser($ana)->allows('update', $luis));
    }

    public function test_la_policy_solo_deja_listar_cuentas_a_un_administrador(): void
    {
        $usuario = User::factory()->create();
        $admin = User::factory()->administrador()->create();

        $this->assertFalse(Gate::forUser($usuario)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', User::class));
    }

    public function test_no_existe_una_ruta_de_perfil_por_identificador(): void
    {
        $usuario = User::factory()->create();
        $otro = User::factory()->create(['nombre_completo' => 'Perfil Ajeno']);

        // La ruta con id de otra persona sencillamente no existe.
        $this->actingAs($usuario)->get("/perfil/{$otro->id}")->assertNotFound();
        $this->actingAs($usuario)->patch("/perfil/{$otro->id}")->assertNotFound();
    }

    public function test_pasar_un_id_por_querystring_devuelve_el_perfil_propio(): void
    {
        $usuario = User::factory()->create(['nombre_completo' => 'Perfil Propio']);
        $otro = User::factory()->create(['nombre_completo' => 'Perfil Ajeno']);

        $response = $this->actingAs($usuario)->get("/perfil?id={$otro->id}");

        $response->assertOk();
        $response->assertSee('Perfil Propio');
        $response->assertDontSee('Perfil Ajeno');
    }

    public function test_inyectar_el_id_de_otra_cuenta_en_el_formulario_no_toca_a_esa_cuenta(): void
    {
        $ana = User::factory()->create(['nombre_completo' => 'Ana Original']);
        $luis = User::factory()->create(['nombre_completo' => 'Luis Original']);

        $this->actingAs($ana)->patch('/perfil', [
            // Intento de secuestro: apuntar a la cuenta de Luis.
            'id' => $luis->id,
            'usuario_id' => $luis->id,
            'nombre_completo' => 'Ana Modificada',
            'numero_identificacion' => $ana->numero_identificacion,
            'email' => $ana->email,
            'telefono' => $ana->telefono,
            'direccion' => $ana->direccion,
            'ocupacion' => $ana->ocupacion,
            'ingresos_mensuales' => $ana->ingresos_mensuales,
            'entidad_bancaria' => $ana->entidad_bancaria,
        ])->assertSessionHasNoErrors();

        // Se modificó la cuenta de quien hizo la petición...
        $this->assertSame('Ana Modificada', $ana->refresh()->nombre_completo);
        // ...y la de Luis quedó exactamente igual.
        $this->assertSame('Luis Original', $luis->refresh()->nombre_completo);
    }

    public function test_no_se_puede_reasignar_la_tarjeta_a_otra_cuenta(): void
    {
        $ana = $this->crearCuentaConTarjeta(['email' => 'ana@example.test']);
        $luis = $this->crearCuentaConTarjeta(
            ['email' => 'luis@example.test'],
            ['numero_tarjeta' => '5555555555554444', 'ultimos_4_digitos' => '4444']
        );

        $this->actingAs($ana)->put('/perfil/tarjeta', [
            'usuario_id' => $luis->id,
            'tarjeta_id' => $luis->tarjeta->id,
            'numero_tarjeta' => '4012888888881881',
            'fecha_vencimiento' => '01/30',
            'nombre_titular' => 'Ana',
        ])->assertSessionHasNoErrors();

        // La tarjeta de Luis sigue siendo la suya.
        $this->assertSame('5555555555554444', $luis->tarjeta()->first()->numero_tarjeta);
        $this->assertSame($luis->id, $luis->tarjeta()->first()->usuario_id);

        // Y la de Ana es la que se actualizó.
        $this->assertSame('4012888888881881', $ana->tarjeta()->first()->numero_tarjeta);

        // No se creó ninguna tarjeta de más.
        $this->assertDatabaseCount('tarjetas', 2);
    }

    public function test_no_se_puede_ver_la_tarjeta_de_otra_cuenta(): void
    {
        $ana = $this->crearCuentaConTarjeta(['email' => 'ana@example.test']);
        $this->crearCuentaConTarjeta(
            ['email' => 'luis@example.test'],
            ['numero_tarjeta' => '5555555555554444', 'ultimos_4_digitos' => '4444']
        );

        $response = $this->actingAs($ana)->get('/perfil/tarjeta');

        $response->assertOk();
        $response->assertSee('**** **** **** 1111');
        $response->assertDontSee('**** **** **** 4444');
    }
}
