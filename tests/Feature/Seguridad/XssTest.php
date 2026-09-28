<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * XSS almacenado (guía, secciones 4 y 7).
 *
 * El escenario real de este sistema: alguien se registra con un nombre como
 * `<script>...</script>` y ese nombre se pinta después en el listado del
 * administrador, que es la pantalla donde un atacante querría ejecutar código
 * con más privilegios que los suyos.
 *
 * El control es el escape automático de Blade: `{{ }}` escapa, `{!! !!}` no.
 * Estas pruebas verifican que se usa el primero.
 */
class XssTest extends TestCase
{
    use RefreshDatabase;

    private const CARGA = '<script>alert("xss")</script>';

    public function test_un_nombre_con_script_se_escapa_en_el_listado_de_administracion(): void
    {
        $admin = User::factory()->administrador()->create();
        User::factory()->create(['nombre_completo' => self::CARGA]);

        $response = $this->actingAs($admin)->get('/admin/usuarios');

        $response->assertOk();

        // El HTML crudo NO contiene la etiqueta ejecutable...
        $response->assertDontSee(self::CARGA, false);
        // ...sino su versión escapada, que el navegador pinta como texto.
        $response->assertSee('&lt;script&gt;', false);
    }

    public function test_un_nombre_con_script_se_escapa_en_el_propio_perfil(): void
    {
        $usuario = User::factory()->create(['nombre_completo' => self::CARGA]);

        $response = $this->actingAs($usuario)->get('/perfil');

        $response->assertDontSee(self::CARGA, false);
        $response->assertSee('&lt;script&gt;', false);
    }

    public function test_un_nombre_con_script_se_escapa_en_la_navegacion(): void
    {
        $usuario = User::factory()->create(['nombre_completo' => self::CARGA]);

        $response = $this->actingAs($usuario)->get('/dashboard');

        $response->assertDontSee(self::CARGA, false);
    }

    public function test_una_direccion_con_script_se_escapa(): void
    {
        $usuario = User::factory()->create([
            'direccion' => '<img src=x onerror=alert(1)>',
        ]);

        $response = $this->actingAs($usuario)->get('/perfil');

        $response->assertDontSee('<img src=x onerror=alert(1)>', false);
        $response->assertSee('&lt;img', false);
    }

    public function test_la_carga_se_guarda_sin_modificar_y_solo_se_escapa_al_mostrarla(): void
    {
        $this->post('/register', $this->datosRegistro([
            'nombre_completo' => self::CARGA,
        ]));

        // Lo que hay en la base de datos es el texto tal cual: el escape es una
        // decisión de la capa de salida, no una mutilación del dato de entrada.
        $this->assertSame(self::CARGA, User::firstWhere('email', 'persona@example.test')->nombre_completo);
    }
}
