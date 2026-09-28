<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Escalada de privilegios vertical (guía, secciones 4 y 5).
 *
 * El vector es la asignación masiva: si `role` estuviera en `$fillable`, bastaría
 * con añadir `role=administrador` al POST del registro o al PATCH del perfil
 * para convertirse en administrador sin tocar la base de datos.
 */
class EscaladaPrivilegiosTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_no_es_asignable_en_masa(): void
    {
        $this->assertNotContains('role', (new User)->getFillable());
    }

    public function test_el_registro_ignora_un_role_enviado_en_la_peticion(): void
    {
        $this->post('/register', $this->datosRegistro([
            'role' => 'administrador',
        ]))->assertSessionHasNoErrors();

        $usuario = User::firstWhere('email', 'persona@example.test');

        $this->assertSame('usuario', $usuario->role);
        $this->assertFalse($usuario->esAdministrador());
    }

    public function test_la_actualizacion_de_perfil_ignora_un_role_enviado(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->patch('/perfil', [
            'role' => 'administrador',
            'nombre_completo' => $usuario->nombre_completo,
            'numero_identificacion' => $usuario->numero_identificacion,
            'email' => $usuario->email,
            'telefono' => $usuario->telefono,
            'direccion' => $usuario->direccion,
            'ocupacion' => $usuario->ocupacion,
            'ingresos_mensuales' => $usuario->ingresos_mensuales,
            'entidad_bancaria' => $usuario->entidad_bancaria,
        ])->assertSessionHasNoErrors();

        $this->assertSame('usuario', $usuario->refresh()->role);
    }

    public function test_promoverse_a_mano_no_da_acceso_al_panel_en_la_misma_sesion(): void
    {
        $usuario = User::factory()->create();

        // Se intenta por el registro y por el perfil, y se confirma que
        // ninguna de las dos vías da acceso al panel.
        $this->actingAs($usuario)->patch('/perfil', [
            'role' => 'administrador',
            'nombre_completo' => $usuario->nombre_completo,
            'numero_identificacion' => $usuario->numero_identificacion,
            'email' => $usuario->email,
            'telefono' => $usuario->telefono,
            'direccion' => $usuario->direccion,
            'ocupacion' => $usuario->ocupacion,
            'ingresos_mensuales' => $usuario->ingresos_mensuales,
            'entidad_bancaria' => $usuario->entidad_bancaria,
        ]);

        $this->actingAs($usuario->refresh())
            ->get('/admin/usuarios')
            ->assertForbidden();
    }

    public function test_el_cambio_de_rol_solo_ocurre_por_codigo_de_confianza(): void
    {
        // El camino legítimo: asignación explícita, fuera de la asignación masiva.
        $usuario = User::factory()->create();

        $usuario->role = 'administrador';
        $usuario->save();

        $this->actingAs($usuario)->get('/admin/usuarios')->assertOk();
    }
}
