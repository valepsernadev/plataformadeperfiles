<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Carga útil válida para el formulario de registro, con los campos que
     * exige el enunciado.
     *
     * El número de tarjeta es uno de los de PRUEBA publicados por las
     * pasarelas de pago (cumple Luhn y no corresponde a ninguna tarjeta real).
     *
     * @param  array<string, mixed>  $sobrescribir
     * @return array<string, mixed>
     */
    protected function datosRegistro(array $sobrescribir = []): array
    {
        return array_merge([
            'nombre_completo' => 'Persona de Prueba',
            'numero_identificacion' => '900000001',
            'email' => 'persona@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'telefono' => '3001234567',
            'direccion' => 'Calle Falsa 123',
            'ocupacion' => 'Analista',
            'ingresos_mensuales' => '5000.00',
            'entidad_bancaria' => 'Banco de Prueba',
            'numero_tarjeta' => '4111111111111111',
            'fecha_vencimiento' => '08/29',
            'nombre_titular' => 'Persona de Prueba',
        ], $sobrescribir);
    }

    /**
     * Crea una cuenta con su tarjeta. La tarjeta se crea a través de la
     * relación, igual que hace la aplicación, para que la clave foránea la
     * ponga Eloquent y no un array de atributos.
     *
     * @param  array<string, mixed>  $atributos
     * @param  array<string, mixed>  $tarjeta
     */
    protected function crearCuentaConTarjeta(array $atributos = [], array $tarjeta = []): User
    {
        $usuario = User::factory()->create($atributos);

        $usuario->tarjeta()->create(array_merge([
            'numero_tarjeta' => '4111111111111111',
            'ultimos_4_digitos' => '1111',
            'nombre_titular' => $usuario->nombre_completo,
            'fecha_vencimiento' => '08/29',
        ], $tarjeta));

        return $usuario;
    }
}
