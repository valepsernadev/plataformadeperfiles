<?php

namespace Tests\Feature\Seguridad;

use App\Models\Tarjeta;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cifrado del número de tarjeta (guía, secciones 2, 7 y 9).
 *
 * El número de tarjeta es el único dato clasificado como **Restringido** y el
 * único que se cifra a nivel de aplicación. Lo que se comprueba aquí es que el
 * cifrado es real: que un volcado de la base de datos no exponga el número y
 * que sin la APP_KEY el criptograma sea inservible.
 */
class CifradoTarjetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_numero_no_se_guarda_en_texto_plano(): void
    {
        $this->crearCuentaConTarjeta([], ['numero_tarjeta' => '4111111111111111']);

        $crudo = DB::table('tarjetas')->value('numero_tarjeta');

        $this->assertNotSame('4111111111111111', $crudo);
        $this->assertStringNotContainsString('4111111111111111', $crudo);
        $this->assertStringNotContainsString('4111', $crudo);
    }

    public function test_eloquent_descifra_al_leer(): void
    {
        $usuario = $this->crearCuentaConTarjeta([], ['numero_tarjeta' => '4111111111111111']);

        $this->assertSame('4111111111111111', $usuario->tarjeta()->first()->numero_tarjeta);
    }

    public function test_los_ultimos_cuatro_digitos_si_estan_en_claro(): void
    {
        $this->crearCuentaConTarjeta([], [
            'numero_tarjeta' => '5555555555554444',
            'ultimos_4_digitos' => '4444',
        ]);

        // Es intencional (guía, sección 9): permite pintar la tarjeta
        // enmascarada sin descifrar en cada render.
        $this->assertSame('4444', DB::table('tarjetas')->value('ultimos_4_digitos'));
    }

    public function test_el_numero_completo_nunca_se_muestra_en_la_interfaz(): void
    {
        $usuario = $this->crearCuentaConTarjeta();

        $response = $this->actingAs($usuario)->get('/perfil');

        $response->assertSee('**** **** **** 1111');
        $response->assertDontSee('4111111111111111');
    }

    public function test_dos_tarjetas_con_el_mismo_numero_producen_criptogramas_distintos(): void
    {
        $this->crearCuentaConTarjeta(['email' => 'una@example.test'], ['numero_tarjeta' => '4111111111111111']);
        $this->crearCuentaConTarjeta(['email' => 'otra@example.test'], ['numero_tarjeta' => '4111111111111111']);

        $criptogramas = DB::table('tarjetas')->pluck('numero_tarjeta');

        // Laravel usa un vector de inicialización aleatorio en cada cifrado, así
        // que el mismo número no produce el mismo criptograma. Eso impide que
        // alguien sin la clave pueda siquiera saber qué cuentas comparten tarjeta.
        $this->assertCount(2, $criptogramas);
        $this->assertNotSame($criptogramas[0], $criptogramas[1]);
    }

    public function test_sin_la_app_key_el_criptograma_es_inservible(): void
    {
        $this->crearCuentaConTarjeta([], ['numero_tarjeta' => '4111111111111111']);

        $crudo = DB::table('tarjetas')->value('numero_tarjeta');

        // Se intenta descifrar con otra clave cualquiera: exactamente lo que
        // tendría un atacante que consiguiera un volcado de la base de datos
        // pero no el .env.
        $descifradorAjeno = new Encrypter(
            Encrypter::generateKey(config('app.cipher')),
            config('app.cipher')
        );

        $this->expectException(DecryptException::class);
        $descifradorAjeno->decryptString($crudo);
    }

    public function test_solo_el_numero_de_tarjeta_esta_cifrado(): void
    {
        $usuario = $this->crearCuentaConTarjeta([
            'ocupacion' => 'Analista',
            'entidad_bancaria' => 'Banco de Prueba',
        ]);

        // El resto de campos confidenciales se protegen con autorización, no
        // con cifrado (guía, sección 10: no se cifra cada campo confidencial).
        $this->assertSame('Banco de Prueba', DB::table('usuarios')->where('id', $usuario->id)->value('entidad_bancaria'));
        $this->assertSame('Analista', DB::table('usuarios')->where('id', $usuario->id)->value('ocupacion'));

        // Y el cast del modelo lo confirma.
        $casts = (new Tarjeta)->getCasts();

        $this->assertSame('encrypted', $casts['numero_tarjeta']);
        $this->assertArrayNotHasKey('nombre_titular', $casts);
    }
}
