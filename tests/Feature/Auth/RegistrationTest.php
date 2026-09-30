<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pantalla_de_registro_se_renderiza(): void
    {
        $this->get('/register')->assertStatus(200);
    }

    public function test_una_cuenta_nueva_puede_registrarse_con_todos_sus_datos(): void
    {
        $response = $this->post('/register', $this->datosRegistro());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $usuario = User::firstWhere('email', 'persona@example.test');

        $this->assertNotNull($usuario);
        $this->assertSame('Persona de Prueba', $usuario->nombre_completo);
        $this->assertSame('900000001', $usuario->numero_identificacion);
        $this->assertSame('Banco de Prueba', $usuario->entidad_bancaria);
        $this->assertSame('5000.00', $usuario->ingresos_mensuales);
    }

    public function test_el_registro_crea_la_tarjeta_asociada(): void
    {
        $this->post('/register', $this->datosRegistro());

        $usuario = User::firstWhere('email', 'persona@example.test');

        $this->assertNotNull($usuario->tarjeta);
        $this->assertSame('4111111111111111', $usuario->tarjeta->numero_tarjeta);
        $this->assertSame('1111', $usuario->tarjeta->ultimos_4_digitos);
        $this->assertSame('08/29', $usuario->tarjeta->fecha_vencimiento);
    }

    public function test_toda_cuenta_nueva_nace_con_rol_usuario(): void
    {
        $this->post('/register', $this->datosRegistro());

        $this->assertSame('usuario', User::firstWhere('email', 'persona@example.test')->role);
    }

    public function test_el_registro_exige_los_campos_obligatorios(): void
    {
        $response = $this->post('/register', []);

        $response->assertSessionHasErrors([
            'nombre_completo',
            'numero_identificacion',
            'email',
            'password',
            'telefono',
            'direccion',
            'ocupacion',
            'ingresos_mensuales',
            'entidad_bancaria',
            'numero_tarjeta',
            'fecha_vencimiento',
            'nombre_titular',
        ]);

        $this->assertGuest();
    }

    public function test_no_se_acepta_un_numero_de_tarjeta_que_falla_luhn(): void
    {
        // 4111111111111112 es el número de prueba anterior con el último dígito
        // cambiado: el dígito de control ya no cuadra.
        $response = $this->post('/register', $this->datosRegistro([
            'numero_tarjeta' => '4111111111111112',
        ]));

        $response->assertSessionHasErrors('numero_tarjeta');
        $this->assertDatabaseCount('usuarios', 0);
        $this->assertDatabaseCount('tarjetas', 0);
    }

    public function test_se_acepta_el_numero_de_tarjeta_con_espacios_y_se_normaliza(): void
    {
        $this->post('/register', $this->datosRegistro([
            'numero_tarjeta' => '4111 1111 1111 1111',
        ]));

        $this->assertSame('4111111111111111', User::firstWhere('email', 'persona@example.test')->tarjeta->numero_tarjeta);
    }

    public function test_la_fecha_de_vencimiento_debe_tener_formato_mm_aa(): void
    {
        $response = $this->post('/register', $this->datosRegistro([
            'fecha_vencimiento' => '13/2029',
        ]));

        $response->assertSessionHasErrors('fecha_vencimiento');
    }

    public function test_no_se_guarda_ningun_codigo_de_seguridad_aunque_se_envie(): void
    {
        $this->post('/register', $this->datosRegistro([
            'cvv' => '123',
            'cvc' => '456',
            'codigo_seguridad' => '789',
        ]));

        // La tabla `tarjetas` no tiene ninguna columna donde pudiera caber.
        $columnas = DB::connection()->getSchemaBuilder()->getColumnListing('tarjetas');

        $this->assertNotContains('cvv', $columnas);
        $this->assertNotContains('cvc', $columnas);
        $this->assertNotContains('codigo_seguridad', $columnas);

        // Y ningún valor enviado quedó almacenado en la fila creada.
        $fila = (array) DB::table('tarjetas')->first();

        $this->assertNotContains('123', $fila);
        $this->assertNotContains('456', $fila);
        $this->assertNotContains('789', $fila);
    }

    public function test_no_se_puede_reutilizar_un_correo_ya_registrado(): void
    {
        User::factory()->create(['email' => 'ocupado@example.test']);

        $response = $this->post('/register', $this->datosRegistro([
            'email' => 'ocupado@example.test',
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('usuarios', 1);
    }

    // -----------------------------------------------------------------------
    // Regla del nombre (App\Rules\NombrePropio)
    // -----------------------------------------------------------------------

    public function test_el_nombre_no_admite_digitos(): void
    {
        $response = $this->post('/register', $this->datosRegistro([
            'nombre_completo' => 'Juan Pérez 123',
        ]));

        $response->assertSessionHasErrors('nombre_completo');
        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_el_nombre_no_admite_etiquetas_ni_simbolos(): void
    {
        foreach (['<b>Ana</b>', 'Ana@casa', 'Ana_Gómez', 'Ana#1'] as $invalido) {
            $this->post('/register', $this->datosRegistro([
                'nombre_completo' => $invalido,
            ]))->assertSessionHasErrors('nombre_completo');
        }

        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_el_nombre_admite_tildes_apostrofos_y_guiones(): void
    {
        $response = $this->post('/register', $this->datosRegistro([
            'nombre_completo' => "María José O'Brien-Pérez",
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertSame(
            "María José O'Brien-Pérez",
            User::firstWhere('email', 'persona@example.test')->nombre_completo
        );
    }

    /**
     * Un espacio de más al pegar no debe convertirse en un error de validación:
     * la normalización previa lo colapsa antes de comprobar el patrón.
     */
    public function test_los_espacios_de_mas_en_el_nombre_se_normalizan(): void
    {
        $response = $this->post('/register', $this->datosRegistro([
            'nombre_completo' => '  María   José  ',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertSame(
            'María José',
            User::firstWhere('email', 'persona@example.test')->nombre_completo
        );
    }

    // -----------------------------------------------------------------------
    // Política de contraseñas (AppServiceProvider::configurarPoliticaDeContrasenas)
    // -----------------------------------------------------------------------

    public function test_la_contrasena_exige_un_numero(): void
    {
        $this->post('/register', $this->datosRegistro([
            'password' => 'SinNumeros!',
            'password_confirmation' => 'SinNumeros!',
        ]))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_la_contrasena_exige_un_simbolo(): void
    {
        $this->post('/register', $this->datosRegistro([
            'password' => 'SinSimbolo123',
            'password_confirmation' => 'SinSimbolo123',
        ]))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_la_contrasena_exige_una_longitud_minima(): void
    {
        $this->post('/register', $this->datosRegistro([
            'password' => 'Ab1!',
            'password_confirmation' => 'Ab1!',
        ]))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_una_contrasena_que_cumple_la_politica_se_acepta(): void
    {
        $this->post('/register', $this->datosRegistro([
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]))->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }
}
