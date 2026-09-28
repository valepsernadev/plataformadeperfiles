<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rate limiting del login (guía, secciones 4, 5 y 7).
 *
 * Se cubren las dos amenazas por separado:
 *
 * - Fuerza bruta contra una cuenta concreta -> límite de Breeze por `email|ip`.
 * - Credential stuffing (muchos correos desde una máquina) -> límite por IP
 *   definido en AppServiceProvider.
 */
class RateLimitLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_cuenta_se_bloquea_tras_cinco_intentos_fallidos(): void
    {
        $usuario = User::factory()->create(['email' => 'objetivo@example.test']);

        for ($intento = 0; $intento < 5; $intento++) {
            $this->post('/login', [
                'email' => 'objetivo@example.test',
                'password' => 'contrasena-incorrecta',
            ]);
        }

        // El sexto intento ya ni siquiera evalúa las credenciales.
        $response = $this->post('/login', [
            'email' => 'objetivo@example.test',
            'password' => 'contrasena-incorrecta',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        // Y la contraseña correcta tampoco sirve mientras dure el bloqueo.
        $this->post('/login', [
            'email' => 'objetivo@example.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_el_contador_se_reinicia_tras_un_acceso_correcto(): void
    {
        $usuario = User::factory()->create(['email' => 'persona@example.test']);

        for ($intento = 0; $intento < 3; $intento++) {
            $this->post('/login', [
                'email' => 'persona@example.test',
                'password' => 'contrasena-incorrecta',
            ]);
        }

        $this->post('/login', [
            'email' => 'persona@example.test',
            'password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_el_limite_por_ip_frena_el_credential_stuffing(): void
    {
        // 20 intentos, cada uno contra un correo distinto: el límite por
        // `email|ip` de Breeze no los ve, porque cada correo abre su propio
        // contador. El límite por IP sí.
        for ($intento = 0; $intento < 20; $intento++) {
            $this->post('/login', [
                'email' => "victima{$intento}@example.test",
                'password' => 'probando-contrasenas',
            ]);
        }

        $this->post('/login', [
            'email' => 'victima20@example.test',
            'password' => 'probando-contrasenas',
        ])->assertStatus(429);
    }

    public function test_el_limite_por_ip_no_estorba_a_un_acceso_normal(): void
    {
        $usuario = User::factory()->create(['email' => 'normal@example.test']);

        $this->post('/login', [
            'email' => 'normal@example.test',
            'password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($usuario);
    }
}
