<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Soft delete (guía, secciones 7 y 9).
 *
 * "Eliminar" una cuenta desde el panel de administración no borra la fila
 * físicamente: se marca `deleted_at`. La cuenta deja de ser accesible, pero el
 * registro se conserva para auditoría.
 */
class BorradoLogicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_eliminar_desde_el_panel_marca_deleted_at_y_conserva_la_fila(): void
    {
        $admin = User::factory()->administrador()->create();
        $objetivo = User::factory()->create();

        $this->actingAs($admin)->delete("/admin/usuarios/{$objetivo->id}");

        $this->assertSoftDeleted('usuarios', ['id' => $objetivo->id]);

        // La fila sigue físicamente en la base de datos, con su marca.
        $fila = DB::table('usuarios')->where('id', $objetivo->id)->first();

        $this->assertNotNull($fila);
        $this->assertNotNull($fila->deleted_at);
    }

    public function test_el_modelo_deja_de_encontrar_la_cuenta_eliminada(): void
    {
        $admin = User::factory()->administrador()->create();
        $objetivo = User::factory()->create();

        $this->actingAs($admin)->delete("/admin/usuarios/{$objetivo->id}");

        $this->assertNull(User::find($objetivo->id));
        // Pero sigue siendo recuperable si hiciera falta auditar.
        $this->assertNotNull(User::withTrashed()->find($objetivo->id));
    }

    public function test_una_cuenta_eliminada_no_puede_iniciar_sesion(): void
    {
        // El borrado lógico se hace aquí directamente sobre el modelo, sin
        // pasar por el panel: así la petición de login parte de una sesión
        // limpia y el rechazo solo puede deberse a que la cuenta no existe
        // para el proveedor de autenticación.
        $objetivo = User::factory()->create(['email' => 'eliminada@example.test']);
        $objetivo->delete();

        $this->post('/login', [
            'email' => 'eliminada@example.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_una_cuenta_eliminada_sigue_en_el_listado_marcada_y_sin_acciones(): void
    {
        $admin = User::factory()->administrador()->create();
        $objetivo = User::factory()->create(['nombre_completo' => 'Cuenta Que Se Va']);

        $this->actingAs($admin)->delete("/admin/usuarios/{$objetivo->id}");

        $response = $this->actingAs($admin)->get('/admin/usuarios');

        // Sigue apareciendo en el listado, marcada como eliminada...
        $response->assertSee('Cuenta Que Se Va');
        $response->assertSee('Eliminado');

        // ...pero su fila no ofrece ninguna acción.
        $response->assertDontSee("/admin/usuarios/{$objetivo->id}", false);
    }

    public function test_la_tarjeta_de_una_cuenta_eliminada_no_se_borra_fisicamente(): void
    {
        $admin = User::factory()->administrador()->create();
        $objetivo = $this->crearCuentaConTarjeta();

        $this->actingAs($admin)->delete("/admin/usuarios/{$objetivo->id}");

        $this->assertDatabaseHas('tarjetas', ['usuario_id' => $objetivo->id]);
    }

    public function test_un_administrador_no_puede_eliminarse_a_si_mismo(): void
    {
        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)
            ->from('/admin/usuarios')
            ->delete("/admin/usuarios/{$admin->id}")
            ->assertSessionHasErrors('usuario');

        $this->assertNotSoftDeleted('usuarios', ['id' => $admin->id]);
    }
}
