<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Captcha del registro y del login (guía, secciones 4 y 5).
 *
 * La amenaza: ataques automatizados de suplantación — fuerza bruta y credential
 * stuffing — que hasta ahora solo frenaba el rate limiting. El captcha añade
 * una barrera que exige intervención humana *antes* de que la petición llegue
 * siquiera a comprobar credenciales.
 *
 * CÓMO SE PRUEBA, que tiene dos piezas y conviene entenderlas juntas:
 *
 * 1. `App\Rules\Recaptcha` NO valida nada cuando el entorno es `testing`. Sin
 *    ese corte habría que sembrar un token en cada una de las ~45 peticiones a
 *    /login y /register de la suite, y la suite entera dependería de internet.
 *    La primera prueba de este archivo documenta ese corte.
 *
 * 2. Para comprobar el control de verdad, se cambia el entorno a `local` —el
 *    mismo truco que usa CsrfTest.php— y se responde con `Http::fake()`. Nada
 *    de esto toca la red: `Http::preventStrayRequests()` hace que cualquier
 *    llamada no simulada falle en vez de salir a internet por accidente.
 *
 * Con las claves de prueba de Google el captcha siempre diría que sí. Aquí se
 * simula a mano justamente para poder probar también el caso en que dice que
 * no, que es el que con las claves de prueba nunca ocurriría.
 */
class CaptchaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Al cambiar el entorno a `local`, el middleware CSRF deja de estar
     * desactivado (Laravel solo lo desactiva en pruebas), así que hace falta
     * enviar un token válido o el rechazo sería un 419 y no el del captcha.
     * Es el mismo procedimiento que CsrfTest.php.
     *
     * @param  array<string, mixed>  $datos
     */
    private function peticionConCsrf(string $uri, array $datos)
    {
        $this->app['env'] = 'local';

        return $this->withSession(['_token' => 'token-de-prueba'])
            ->post($uri, [...$datos, '_token' => 'token-de-prueba']);
    }

    public function test_el_captcha_se_omite_en_el_entorno_de_pruebas(): void
    {
        // Sin tocar el entorno no se exige captcha: es lo que permite que el
        // resto de la suite (registro, login, rate limiting, hashing...) siga
        // probando lo suyo sin sembrar un token en cada petición.
        $this->post('/register', $this->datosRegistro())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('usuarios', 1);
    }

    public function test_un_registro_sin_captcha_resuelto_es_rechazado(): void
    {
        $response = $this->peticionConCsrf('/register', $this->datosRegistro());

        $response->assertSessionHasErrors('g-recaptcha-response');

        // El rechazo ocurre antes de crear nada: no hay cuenta a medias.
        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_un_login_sin_captcha_resuelto_es_rechazado(): void
    {
        $usuario = User::factory()->create();

        $response = $this->peticionConCsrf('/login', [
            'email' => $usuario->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('g-recaptcha-response');

        // Credenciales correctas y aun así no entra: el captcha va antes.
        $this->assertGuest();
    }

    public function test_un_captcha_que_google_rechaza_no_deja_entrar(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['success' => false])]);

        $usuario = User::factory()->create();

        $response = $this->peticionConCsrf('/login', [
            'email' => $usuario->email,
            'password' => 'password',
            'g-recaptcha-response' => 'token-invalido',
        ]);

        $response->assertSessionHasErrors('g-recaptcha-response');

        $this->assertGuest();
    }

    public function test_un_captcha_valido_permite_iniciar_sesion(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['success' => true])]);

        $usuario = User::factory()->create();

        $this->peticionConCsrf('/login', [
            'email' => $usuario->email,
            'password' => 'password',
            'g-recaptcha-response' => 'token-valido',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_un_captcha_valido_permite_registrar_una_cuenta(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['success' => true])]);

        $this->peticionConCsrf('/register', $this->datosRegistro([
            'g-recaptcha-response' => 'token-valido',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('usuarios', 1);
    }

    /**
     * Falla en cerrado: es la decisión documentada en App\Rules\Recaptcha. Si
     * Google no responde, se rechaza el intento en vez de dejarlo pasar.
     */
    public function test_si_google_no_responde_se_rechaza_el_intento(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('', 500)]);

        $usuario = User::factory()->create();

        $this->peticionConCsrf('/login', [
            'email' => $usuario->email,
            'password' => 'password',
            'g-recaptcha-response' => 'token-cualquiera',
        ])->assertSessionHasErrors('g-recaptcha-response');

        $this->assertGuest();
    }

    /**
     * El widget tiene que estar en los dos formularios. Se comprueba también
     * que se cargue el script de Google, porque el widget no funciona sin él y
     * ese script se inyecta por el stack `scripts` del layout guest: si el
     * stack dejara de existir, este test lo detecta.
     */
    public function test_el_widget_se_dibuja_en_los_dos_formularios(): void
    {
        $clavePublica = config('services.recaptcha.site_key');

        $this->assertNotEmpty($clavePublica, 'Falta RECAPTCHA_SITE_KEY en la configuración.');

        foreach (['/login', '/register'] as $ruta) {
            $response = $this->get($ruta);

            $response->assertOk();
            $response->assertSee('g-recaptcha', false);
            $response->assertSee($clavePublica, false);
            $response->assertSee('google.com/recaptcha/api.js', false);
        }
    }
}
