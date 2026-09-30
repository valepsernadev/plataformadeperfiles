<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Protección CSRF (guía, secciones 4, 5 y 7).
 *
 * La amenaza: que un sitio ajeno haga que el navegador de la víctima envíe un
 * PATCH a /perfil (o un POST a /perfil/tarjeta) aprovechando su sesión abierta.
 *
 * NOTA sobre cómo se prueba: Laravel desactiva la verificación CSRF cuando
 * detecta que corre en tests, para no obligar a enviar el token en cada
 * petición de prueba. Esa comodidad impediría comprobar que el control existe,
 * así que en la prueba correspondiente se cambia el entorno de la aplicación a
 * uno que no sea `testing` y se lanza la petición sin token.
 */
class CsrfTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_middleware_csrf_esta_registrado_en_el_grupo_web(): void
    {
        // En Laravel 13 el middleware se llama PreventRequestForgery (antes
        // VerifyCsrfToken y luego ValidateCsrfToken). Se lee del kernel, que es
        // quien arma los grupos, en vez del router.
        $kernel = app(Kernel::class);
        $grupos = (fn () => $this->middlewareGroups)->call($kernel);

        $this->assertContains(PreventRequestForgery::class, $grupos['web']);
    }

    public function test_un_post_al_registro_sin_token_es_rechazado_con_419(): void
    {
        // Middleware CSRF activo de verdad, como en producción.
        $this->app['env'] = 'local';

        $this->post('/register', $this->datosRegistro())->assertStatus(419);

        // Y no se creó nada.
        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_un_patch_al_perfil_sin_token_es_rechazado_con_419(): void
    {
        $usuario = User::factory()->create(['nombre_completo' => 'Nombre Original']);

        // La sesión se autentica antes de activar la verificación, para que el
        // rechazo se deba únicamente al token ausente.
        $this->actingAs($usuario);

        $this->app['env'] = 'local';

        $this->patch('/perfil', [
            'nombre_completo' => 'Nombre Cambiado',
        ])->assertStatus(419);

        $this->assertSame('Nombre Original', $usuario->refresh()->nombre_completo);
    }

    public function test_los_formularios_incluyen_el_campo_token(): void
    {
        $this->get('/register')->assertSee('name="_token"', false);
        $this->get('/login')->assertSee('name="_token"', false);

        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/perfil')->assertSee('name="_token"', false);
        $this->actingAs($usuario)->get('/perfil/tarjeta')->assertSee('name="_token"', false);
    }

    public function test_con_el_token_correcto_la_peticion_si_pasa(): void
    {
        // Pasar el entorno a `local` activa el CSRF de verdad, pero de paso
        // saca la petición del entorno de pruebas y con ello activa TAMBIÉN el
        // captcha (ver App\Rules\Recaptcha). Como aquí lo que se prueba es el
        // token CSRF, el captcha se resuelve con un doble y se envía su campo:
        // así la única variable en juego sigue siendo el `_token`.
        Http::fake(['*' => Http::response(['success' => true])]);

        $this->app['env'] = 'local';

        // `withSession` + token real: se simula el envío del formulario tal y
        // como lo haría el navegador.
        $response = $this->withSession(['_token' => 'token-de-prueba'])
            ->post('/register', $this->datosRegistro([
                '_token' => 'token-de-prueba',
                'g-recaptcha-response' => 'token-de-prueba',
            ]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('usuarios', 1);
    }
}
