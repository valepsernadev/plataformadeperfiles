<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Contraseña compartida por las cuentas generadas, ya hasheada con bcrypt
     * para no repetir el cálculo en cada modelo.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Se compone a mano con `firstName` + `lastName` en vez de usar
            // `fake()->name()`, que en el locale en_US puede devolver un título
            // ("Dr. Sonny Hessel"). Varias pruebas envían este nombre por
            // `PATCH /perfil`, donde ahora lo valida la regla `NombrePropio`, y
            // el punto del título la haría fallar de forma intermitente.
            'nombre_completo' => fake()->firstName().' '.fake()->lastName(),
            'numero_identificacion' => fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'telefono' => fake()->numerify('3#########'),
            'direccion' => fake()->streetAddress(),
            'ocupacion' => fake()->jobTitle(),
            'ingresos_mensuales' => fake()->randomFloat(2, 500, 20000),
            'entidad_bancaria' => fake()->company(),
            'role' => 'usuario',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Cuenta con rol de administrador.
     *
     * Las factories de Laravel envuelven la creación en `Model::unguarded()`,
     * así que aquí sí se puede fijar `role` aunque no esté en `$fillable`. Ese
     * atajo existe SOLO en código de confianza (pruebas y seeders): el camino
     * que recorre la entrada del usuario nunca pasa por aquí.
     */
    public function administrador(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'administrador',
        ]);
    }
}
