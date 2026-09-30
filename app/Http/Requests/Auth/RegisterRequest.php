<?php

namespace App\Http\Requests\Auth;

use App\Rules\Luhn;
use App\Rules\NombrePropio;
use App\Rules\Recaptcha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validación del formulario de registro (guía, sección 7).
 *
 * Tres decisiones que son controles de seguridad, no estilo:
 *
 * 1. NO existe ninguna regla para `cvv` ni para ningún código de seguridad.
 *    El CVV no se pide, no se valida y no se guarda (guía, sección 10). Como
 *    además el controlador solo escribe los campos de `validated()`, enviarlo
 *    en el POST no tiene ningún efecto.
 *
 * 2. NO existe ninguna regla para `role`, así que un POST con
 *    `role=administrador` se ignora por completo: la lista blanca de
 *    `User::$fillable` y este `validated()` lo descartan.
 *
 * 3. `nombre_completo` lleva la regla `NombrePropio` (solo letras, espacios,
 *    apóstrofos y guiones) y el formulario exige un captcha resuelto. La
 *    contraseña no lleva reglas propias: hereda la política central de
 *    `AppServiceProvider::configurarPoliticaDeContrasenas()`.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ruta pública de registro, protegida por el middleware `guest`
    }

    /**
     * Normaliza la entrada antes de validarla:
     *
     * - `numero_tarjeta`: acepta tanto `4111 1111 1111 1111` como
     *   `4111-1111-1111-1111`. Lo que se guarda es la cadena de dígitos.
     * - `nombre_completo`: recorta los espacios de los extremos y colapsa los
     *   repetidos. Sin esto, un nombre pegado desde otro sitio con un espacio
     *   de más (`"Juan  Pérez"`) sería rechazado por `NombrePropio` con un error
     *   confuso para quien lo escribió, cuando en realidad el nombre está bien.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('numero_tarjeta')) {
            $this->merge([
                'numero_tarjeta' => preg_replace('/\D/', '', (string) $this->input('numero_tarjeta')),
            ]);
        }

        if ($this->has('nombre_completo')) {
            $this->merge([
                'nombre_completo' => NombrePropio::normalizar($this->input('nombre_completo')),
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
            'nombre_completo' => ['required', 'string', 'max:150', new NombrePropio],
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

            // --- Captcha (guía, secciones 4 y 5) ---
            // `g-recaptcha-response` es el nombre real del campo que envía el
            // widget de reCAPTCHA v2, no uno inventado. La regla se encarga
            // tanto de que el campo venga como de que Google lo valide.
            'g-recaptcha-response' => [new Recaptcha],
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
