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
 * Ese escenario se corta ahora en DOS capas, y este archivo prueba las dos:
 *
 *  1. ENTRADA — `nombre_completo` lleva la regla `NombrePropio`, así que el
 *     formulario de registro ya no acepta el nombre con etiquetas. Es lo que
 *     comprueba `test_el_registro_rechaza_un_nombre_con_etiquetas_html`.
 *
 *  2. SALIDA — el escape automático de Blade: `{{ }}` escapa, `{!! !!}` no. Si
 *     un dato así llegara a la base de datos por otra vía (una importación, un
 *     seeder, una vulnerabilidad distinta), el escape lo neutraliza igual. Los
 *     tests de escape crean el registro directamente con la factoría,
 *     saltándose el formulario, que es justo lo que hace falta para probar esta
 *     capa por separado.
 *
 * La segunda capa sigue siendo la importante para los campos que SÍ son texto
 * libre —dirección, ocupación—, donde una regla de charset no tiene sentido.
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

    /**
     * Capa 1 — el control nuevo. Antes de la regla `NombrePropio`, este POST
     * creaba la cuenta con el `<script>` dentro; ahora ni siquiera pasa la
     * validación.
     */
    public function test_el_registro_rechaza_un_nombre_con_etiquetas_html(): void
    {
        $response = $this->post('/register', $this->datosRegistro([
            'nombre_completo' => self::CARGA,
        ]));

        $response->assertSessionHasErrors('nombre_completo');

        // Y no se creó nada: el rechazo ocurre antes de tocar la base de datos.
        $this->assertDatabaseCount('usuarios', 0);
    }

    /**
     * Capa 2 — el escape es una decisión de la SALIDA, no una mutilación del
     * dato de entrada. Un registro que llega por otra vía se guarda tal cual y
     * se escapa al pintarlo.
     */
    public function test_un_nombre_con_script_creado_fuera_del_formulario_se_guarda_sin_modificar(): void
    {
        $usuario = User::factory()->create(['nombre_completo' => self::CARGA]);

        $this->assertSame(self::CARGA, $usuario->fresh()->nombre_completo);
    }
}
