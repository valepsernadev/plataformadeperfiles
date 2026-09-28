<?php

namespace Tests\Unit;

use App\Rules\Luhn;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Algoritmo de Luhn (guía, sección 7: "Validación de formato de tarjeta antes
 * de guardar").
 *
 * Los números usados aquí son los de PRUEBA que publican las pasarelas de pago
 * para sus entornos de desarrollo. Cumplen Luhn y no corresponden a ninguna
 * tarjeta real.
 */
class LuhnTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function numerosValidos(): array
    {
        return [
            'visa de prueba' => ['4111111111111111'],
            'mastercard de prueba' => ['5555555555554444'],
            'visa de prueba (2)' => ['4012888888881881'],
            'amex de prueba' => ['378282246310005'],
            'con espacios' => ['4111 1111 1111 1111'],
            'con guiones' => ['4111-1111-1111-1111'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function numerosInvalidos(): array
    {
        return [
            'un dígito cambiado' => ['4111111111111112'],
            'otro dígito cambiado' => ['5555555555554445'],
            'todo ceros' => ['0000000000000000'],
            'secuencia simple' => ['1234567890123456'],
            'demasiado corto' => ['41111111111'],
            'vacío' => [''],
            'no numérico' => ['abcdefghijklmnop'],
        ];
    }

    /**
     * Las reglas se aplican junto a `required`, igual que en
     * RegisterRequest y TarjetaUpdateRequest. Hace falta porque Laravel no
     * ejecuta las reglas no implícitas sobre una cadena vacía: de eso se
     * encarga `required`, no `Luhn`.
     *
     * @return array<int, mixed>
     */
    private function reglas(): array
    {
        return ['required', new Luhn];
    }

    #[DataProvider('numerosValidos')]
    public function test_acepta_numeros_que_cumplen_luhn(string $numero): void
    {
        $validador = Validator::make(
            ['numero_tarjeta' => $numero],
            ['numero_tarjeta' => $this->reglas()]
        );

        $this->assertTrue($validador->passes(), "Debería aceptar {$numero}");
    }

    #[DataProvider('numerosInvalidos')]
    public function test_rechaza_numeros_que_fallan_luhn(string $numero): void
    {
        $validador = Validator::make(
            ['numero_tarjeta' => $numero],
            ['numero_tarjeta' => $this->reglas()]
        );

        $this->assertTrue($validador->fails(), "Debería rechazar {$numero}");
    }

    public function test_el_mensaje_de_error_explica_el_motivo(): void
    {
        $validador = Validator::make(
            ['numero_tarjeta' => '4111111111111112'],
            ['numero_tarjeta' => $this->reglas()]
        );

        $this->assertStringContainsString('Luhn', $validador->errors()->first('numero_tarjeta'));
    }

    public function test_rechaza_un_numero_con_todos_los_digitos_iguales(): void
    {
        // `0000000000000000` suma 0, que es múltiplo de 10: pasaría Luhn si no
        // se descartara explícitamente.
        $validador = Validator::make(
            ['numero_tarjeta' => '0000000000000000'],
            ['numero_tarjeta' => $this->reglas()]
        );

        $this->assertTrue($validador->fails());
    }
}
