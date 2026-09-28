<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida el dígito de control de un número de tarjeta con el algoritmo de Luhn
 * (guía de seguridad, sección 7: "Validación de formato de tarjeta antes de guardar").
 *
 * Detiene números mal tecleados o inventados antes de cifrarlos y guardarlos.
 * No valida que la tarjeta exista ni que tenga fondos: eso es del emisor.
 */
class Luhn implements ValidationRule
{
    /**
     * Longitud mínima y máxima aceptada (rangos de ISO/IEC 7812 para tarjetas
     * de pago: 12 a 19 dígitos).
     */
    private const MIN_DIGITOS = 12;

    private const MAX_DIGITOS = 19;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $numero = preg_replace('/\D/', '', (string) $value);

        if ($numero === '' || strlen($numero) < self::MIN_DIGITOS || strlen($numero) > self::MAX_DIGITOS) {
            $fail('El número de tarjeta no es válido.');

            return;
        }

        // `0000000000000000` es matemáticamente válido para Luhn (la suma da 0,
        // que es múltiplo de 10), pero ningún emisor real emite un número con
        // todos los dígitos iguales. Se descarta explícitamente.
        if (preg_match('/^(\d)\1+$/', $numero) === 1) {
            $fail('El número de tarjeta no es válido.');

            return;
        }

        if (! $this->pasaLuhn($numero)) {
            $fail('El número de tarjeta no es válido (falla la verificación de Luhn).');
        }
    }

    /**
     * Recorre los dígitos de derecha a izquierda duplicando uno de cada dos.
     * Si el doble supera 9 se le resta 9 (equivale a sumar sus dos cifras).
     * El número es válido si la suma total es múltiplo de 10.
     */
    private function pasaLuhn(string $numero): bool
    {
        $suma = 0;
        $duplicar = false;

        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $digito = (int) $numero[$i];

            if ($duplicar) {
                $digito *= 2;

                if ($digito > 9) {
                    $digito -= 9;
                }
            }

            $suma += $digito;
            $duplicar = ! $duplicar;
        }

        return $suma % 10 === 0;
    }
}
