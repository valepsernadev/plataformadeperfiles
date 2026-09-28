<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * Atributos de la cookie de sesión (guía, secciones 5 y 7).
 *
 * - HttpOnly: JavaScript no puede leerla, así que un XSS no puede robar la
 *   sesión con `document.cookie`.
 * - Secure: el navegador no la envía por HTTP plano, así que no puede
 *   interceptarse en una red sin cifrar.
 * - SameSite=Strict: no viaja en peticiones iniciadas desde otro sitio, lo que
 *   añade una capa extra frente a CSRF y frente a fuga por `Referer`.
 */
class CookiesSesionTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_configuracion_de_sesion_es_la_exigida(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertTrue(config('session.secure'));
        $this->assertSame('strict', config('session.same_site'));
    }

    public function test_la_cookie_de_sesion_lleva_los_tres_atributos(): void
    {
        $cookie = $this->cookieDeSesion($this->get('/login'));

        $this->assertNotNull($cookie, 'No se emitió cookie de sesión.');
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('strict', strtolower((string) $cookie->getSameSite()));
    }

    public function test_la_cookie_sigue_llevando_los_atributos_tras_iniciar_sesion(): void
    {
        $usuario = User::factory()->create(['email' => 'persona@example.test']);

        $response = $this->post('/login', [
            'email' => 'persona@example.test',
            'password' => 'password',
        ]);

        $cookie = $this->cookieDeSesion($response);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('strict', strtolower((string) $cookie->getSameSite()));
    }

    public function test_la_sesion_tiene_caducidad_por_inactividad(): void
    {
        // Una sesión sin expiración es una vulnerabilidad listada en la guía.
        $this->assertGreaterThan(0, config('session.lifetime'));
        $this->assertLessThanOrEqual(480, config('session.lifetime'));
    }

    private function cookieDeSesion($response): ?Cookie
    {
        $nombre = config('session.cookie');

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $nombre) {
                return $cookie;
            }
        }

        return null;
    }
}
