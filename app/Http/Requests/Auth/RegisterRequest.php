<?php

namespace App\Http\Requests\Auth;

use App\Rules\Luhn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validación del formulario de registro (guía, sección 7).
 *
 * Dos decisiones que son controles de seguridad, no estilo:
 *
 * 1. NO existe ninguna regla para `cvv` ni para ningún código de seguridad.
 *    El CVV no se pide, no se valida y no se guarda (guía, sección 10). Como
 *    además el controlador solo escribe los campos de `validated()`, enviarlo
 *    en el POST no tiene ningún efecto.
 *
 * 2. NO existe ninguna regla para `role`, así que un POST con
 *    `role=administrador` se ignora por completo: la lista blanca de
 *    `User::$fillable` y este `validated()` lo descartan.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ruta pública de registro, protegida por el middleware `guest`
    }

    /**
     * Normaliza el número de tarjeta antes de validar, para aceptar tanto
     * `4111 1111 1111 1111` como `4111-1111-1111-1111`. Lo que se guarda es
     * siempre la cadena de dígitos.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('numero_tarjeta')) {
            $this->merge([
                'numero_tarjeta' => preg_replace('/\D/', '', (string) $this->input('numero_tarjeta')),
            ]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // --- Credenciales de acceso (Confidencial) ---
            'email' => ['required', 'string', 'lowercase', 'email', 'max:150', 'unique:usuarios,email'],
            'password' => ['required', 'confirmed', Password::defaults()],

            // --- Datos personales / PII (Confidencial) ---
            'nombre_completo' => ['required', 'string', 'max:150'],
            'numero_identificacion' => ['required', 'string', 'max:20', 'unique:usuarios,numero_identificacion'],
            'telefono' => ['required', 'string', 'max:20'],
            'direccion' => ['required', 'string', 'max:255'],

            // --- Datos financieros (Confidencial) ---
            'ocupacion' => ['required', 'string', 'max:100'],
            // `numeric`, nunca `float`: el valor va a una columna decimal(12,2).
            'ingresos_mensuales' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'entidad_bancaria' => ['required', 'string', 'max:100'],

            // --- Tarjeta (Restringida) ---
            'numero_tarjeta' => ['required', 'string', new Luhn],
            'fecha_vencimiento' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'nombre_titular' => ['required', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_vencimiento.regex' => 'La fecha de vencimiento debe tener el formato MM/YY (por ejemplo 08/29).',
            'email.unique' => 'Ese correo ya está registrado.',
            'numero_identificacion.unique' => 'Ese número de identificación ya está registrado.',
            'email.lowercase' => 'El correo debe escribirse en minúsculas.',
        ];
    }
}
