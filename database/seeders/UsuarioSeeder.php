<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cuentas de demostración:
 *
 *  - 1 administradora (acceso al panel de administración)
 *  - 2 usuarios de prueba
 *  - 2 usuarios del equipo de Analítica
 *
 * Las cinco llevan tarjeta para poder comprobar el cifrado y el enmascarado.
 */
class UsuarioSeeder extends Seeder
{
    /**
     * Contraseña compartida por todas las cuentas sembradas. Son cuentas de
     * demostración: se documentan a propósito para que cualquiera del grupo
     * pueda entrar, y el seeder solo se ejecuta sobre una base de datos de
     * desarrollo.
     */
    public const PASSWORD = 'Password123!';

    public function run(): void
    {
        $this->crear(
            datos: [
                'nombre_completo' => 'Ana Restrepo',
                'numero_identificacion' => '1000000001',
                'email' => 'admin@plataforma.test',
                'telefono' => '3000000001',
                'direccion' => 'Calle 10 #20-30, Medellín',
                'ocupacion' => 'Administradora de la plataforma',
                'ingresos_mensuales' => 8500000.00,
                'entidad_bancaria' => 'Banco de Bogotá',
            ],
            tarjeta: [
                'numero_tarjeta' => '4111111111111111',
                'fecha_vencimiento' => '08/29',
                'nombre_titular' => 'Ana Restrepo',
            ],
            role: 'administrador',
        );

        $this->crear(
            datos: [
                'nombre_completo' => 'Juan Pérez',
                'numero_identificacion' => '1000000002',
                'email' => 'usuario1@plataforma.test',
                'telefono' => '3000000002',
                'direccion' => 'Carrera 45 #12-08, Bogotá',
                'ocupacion' => 'Ingeniero de sistemas',
                'ingresos_mensuales' => 6200000.00,
                'entidad_bancaria' => 'Bancolombia',
            ],
            tarjeta: [
                'numero_tarjeta' => '5555555555554444',
                'fecha_vencimiento' => '11/28',
                'nombre_titular' => 'Juan Pérez',
            ],
        );

        $this->crear(
            datos: [
                'nombre_completo' => 'María Gómez',
                'numero_identificacion' => '1000000003',
                'email' => 'usuario2@plataforma.test',
                'telefono' => '3000000003',
                'direccion' => 'Avenida Siempre Viva 742, Cali',
                'ocupacion' => 'Contadora pública',
                'ingresos_mensuales' => 5400000.50,
                'entidad_bancaria' => 'Davivienda',
            ],
            tarjeta: [
                'numero_tarjeta' => '4012888888881881',
                'fecha_vencimiento' => '03/27',
                'nombre_titular' => 'María Gómez',
            ],
        );

        $this->crear(
            datos: [
                'nombre_completo' => 'Carlos Analítica',
                'numero_identificacion' => '1000000004',
                'email' => 'analitica1@plataforma.test',
                'telefono' => '3000000004',
                'direccion' => 'Calle 50 #30-15, Medellín',
                'ocupacion' => 'Analista de datos',
                'ingresos_mensuales' => 7100000.00,
                'entidad_bancaria' => 'Banco de Occidente',
            ],
            tarjeta: [
                'numero_tarjeta' => '5200828282828210',
                'fecha_vencimiento' => '06/30',
                'nombre_titular' => 'Carlos Analítica',
            ],
        );

        $this->crear(
            datos: [
                'nombre_completo' => 'Laura Analítica',
                'numero_identificacion' => '1000000005',
                'email' => 'analitica2@plataforma.test',
                'telefono' => '3000000005',
                'direccion' => 'Carrera 7 #71-21, Bogotá',
                'ocupacion' => 'Analista de inteligencia de negocio',
                'ingresos_mensuales' => 7800000.00,
                'entidad_bancaria' => 'BBVA Colombia',
            ],
            tarjeta: [
                'numero_tarjeta' => '378282246310005',
                'fecha_vencimiento' => '01/31',
                'nombre_titular' => 'Laura Analítica',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $tarjeta
     */
    private function crear(array $datos, array $tarjeta, string $role = 'usuario'): User
    {
        $usuario = User::create([
            ...$datos,
            // bcrypt, nunca texto plano ni cifrado reversible.
            'password' => Hash::make(self::PASSWORD),
        ]);

        // `role` se fija de forma explícita, NO por asignación masiva: es la
        // misma disciplina que protege al registro y a la edición de perfil.
        $usuario->role = $role;
        $usuario->save();

        // Números de tarjeta de PRUEBA, publicados por las pasarelas de pago
        // para test y que cumplen Luhn. No son tarjetas reales.
        // Se guardan cifrados por el cast `encrypted` del modelo.
        $usuario->tarjeta()->create([
            ...$tarjeta,
            'ultimos_4_digitos' => substr($tarjeta['numero_tarjeta'], -4),
        ]);

        return $usuario;
    }
}
