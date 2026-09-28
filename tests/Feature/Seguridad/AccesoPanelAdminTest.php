<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Control de acceso por rol (guía, secciones 5 y 7).
 *
 * La amenaza es la escalada de privilegios vertical: una cuenta con rol
 * `usuario` consiguiendo funciones de administración. La barrera tiene que
 * estar en el backend, no en el `@if` que oculta el enlace de la navegación.
 */
class AccesoPanelAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_con_rol_usuario_recibe_403_en_el_listado(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get('/admin/usuarios')
            ->assertForbidden();
    }

    public function test_un_usuario_con_rol_usuario_recibe_403_al_intentar_eliminar(): void
    {
        $atacante = User::factory()->create();
        $victima = User::factory()->create();

        $this->actingAs($atacante)
            ->delete("/admin/usuarios/{$victima->id}")
            ->assertForbidden();

        // La cuenta de la víctima sigue intacta.
        $this->assertNotSoftDeleted('usuarios', ['id' => $victima->id]);
    }

    public function test_un_invitado_va_al_login_y_no_al_panel(): void
    {
        $this->get('/admin/usuarios')->assertRedirect('/login');
    }

    public function test_un_administrador_si_puede_ver_el_listado(): void
    {
        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)->get('/admin/usuarios')->assertOk();
    }

    public function test_un_administrador_si_puede_eliminar_una_cuenta(): void
    {
        $admin = User::factory()->administrador()->create();
        $objetivo = User::factory()->create();

        $this->actingAs($admin)
            ->delete("/admin/usuarios/{$objetivo->id}")
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertSoftDeleted('usuarios', ['id' => $objetivo->id]);
    }

    public function test_el_listado_solo_expone_los_datos_minimos(): void
    {
        $admin = User::factory()->administrador()->create();

        $objetivo = $this->crearCuentaConTarjeta([
            'nombre_completo' => 'Objetivo Visible',
            'ocupacion' => 'Ocupacion Reservada',
            'ingresos_mensuales' => '9876543.21',
            'entidad_bancaria' => 'Banco Reservado',
        ]);

        $response = $this->actingAs($admin)->get('/admin/usuarios');

        // Lo que sí debe verse: nombre, correo y cédula.
        $response->assertSee('Objetivo Visible');
        $response->assertSee($objetivo->email);
        $response->assertSee($objetivo->numero_identificacion);

        // Lo que NO debe verse nunca (guía, sección 7).
        $response->assertDontSee('Ocupacion Reservada');
        $response->assertDontSee('9876543.21');
        $response->assertDontSee('Banco Reservado');
        $response->assertDontSee('4111111111111111');
        $response->assertDontSee('****');
    }

    public function test_el_panel_no_carga_las_columnas_sensibles_ni_siquiera_en_memoria(): void
    {
        $admin = User::factory()->administrador()->create();
        $this->crearCuentaConTarjeta(['ingresos_mensuales' => '9876543.21']);

        $this->actingAs($admin)->get('/admin/usuarios')->assertOk();

        // El `select()` explícito del controlador limita las columnas que se
        // consultan, así que aunque un día alguien añadiera un `{{ $u->ingresos_mensuales }}`
        // a la vista, el valor no estaría cargado.
        $this->assertSame(
            ['id', 'nombre_completo', 'email', 'numero_identificacion'],
            User::columnasListadoAdmin()
        );
    }
}
