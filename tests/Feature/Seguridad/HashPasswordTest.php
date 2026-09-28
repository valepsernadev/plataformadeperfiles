<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Almacenamiento de contraseñas (guía, secciones 2, 5 y 7).
 *
 * La contraseña es la llave de acceso a la cuenta y se clasifica como
 * confidencial, pero NO se cifra: se hashea con bcrypt, que es irreversible.
 * La diferencia importa: un cifrado se puede deshacer con la clave, un hash no.
 */
class HashPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_contrasena_se_guarda_como_hash_bcrypt(): void
    {
        $this->post('/register', $this->datosRegistro());

        $crudo = DB::table('usuarios')->value('password');

        $this->assertStringStartsWith('$2y$', $crudo);
        $this->assertNotSame('Password123!', $crudo);
        $this->assertStringNotContainsString('Password123!', $crudo);
        $this->assertTrue(Hash::check('Password123!', $crudo));
    }

    public function test_la_contrasena_no_se_puede_recuperar_del_hash(): void
    {
        $usuario = User::factory()->create();

        $crudo = DB::table('usuarios')->where('id', $usuario->id)->value('password');

        $this->assertFalse(Hash::check('otra-contrasena', $crudo));
    }

    public function test_dos_cuentas_con_la_misma_contrasena_tienen_hashes_distintos(): void
    {
        // Se registran por el flujo real, no con la factory: la factory reutiliza
        // un hash ya calculado entre modelos, así que no serviría para
        // comprobar el salteo de bcrypt.
        $this->post('/register', $this->datosRegistro([
            'email' => 'una@example.test',
            'numero_identificacion' => '900000011',
        ]))->assertSessionHasNoErrors();

        // El registro deja la sesión iniciada, y /register está detrás del
        // middleware `guest`: hay que cerrar sesión antes del segundo.
        $this->post('/logout');
        $this->assertGuest();

        $this->post('/register', $this->datosRegistro([
            'email' => 'otra@example.test',
            'numero_identificacion' => '900000012',
        ]))->assertSessionHasNoErrors();

        $hashes = DB::table('usuarios')->orderBy('id')->pluck('password');

        $this->assertCount(2, $hashes);

        // Misma contraseña ('Password123!'), hashes distintos: bcrypt usa una
        // sal aleatoria por hash, así que dos cuentas con la misma contraseña
        // no se delatan entre sí.
        $this->assertNotSame($hashes[0], $hashes[1]);

        $this->assertTrue(Hash::check('Password123!', $hashes[0]));
        $this->assertTrue(Hash::check('Password123!', $hashes[1]));
    }

    public function test_la_contrasena_nunca_se_serializa_en_la_respuesta(): void
    {
        $usuario = User::factory()->create();

        $this->assertArrayNotHasKey('password', $usuario->toArray());
        $this->assertArrayNotHasKey('remember_token', $usuario->toArray());
    }

    public function test_al_cambiar_la_contrasena_se_vuelve_a_hashear(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->put('/password', [
            'current_password' => 'password',
            'password' => 'NuevaPassword123!',
            'password_confirmation' => 'NuevaPassword123!',
        ])->assertSessionHasNoErrors();

        $crudo = DB::table('usuarios')->where('id', $usuario->id)->value('password');

        $this->assertStringStartsWith('$2y$', $crudo);
        $this->assertTrue(Hash::check('NuevaPassword123!', $crudo));
        $this->assertFalse(Hash::check('password', $crudo));
    }
}
