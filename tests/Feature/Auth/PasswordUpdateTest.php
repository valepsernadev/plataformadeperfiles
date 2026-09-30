<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Contraseña que cumple la política central (8+ caracteres, con letra,
     * número y símbolo). Antes esta prueba usaba `'new-password'`, que la
     * política nueva rechaza.
     */
    private const PASSWORD_NUEVA = 'NuevaPassword123!';

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => self::PASSWORD_NUEVA,
                'password_confirmation' => self::PASSWORD_NUEVA,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check(self::PASSWORD_NUEVA, $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                // Con una contraseña nueva que SÍ cumple la política, para que
                // el único error posible sea el de `current_password` y la
                // prueba siga comprobando lo que dice comprobar.
                'password' => self::PASSWORD_NUEVA,
                'password_confirmation' => self::PASSWORD_NUEVA,
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }
}
