<?php

namespace App\Http\Requests;

use App\Rules\Luhn;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Actualización de la tarjeta (dato Restringido).
 *
 * La tarjeta se modifica contra la tabla `tarjetas`, nunca contra `usuarios`.
 * `usuario_id` no está en las reglas ni en `Tarjeta::$fillable`: la tarjeta se
 * resuelve siempre con `$request->user()->tarjeta()`, así que no hay ningún
 * identificador en el cuerpo ni en la URL que un atacante pueda manipular para
 * tocar la tarjeta de otra persona (IDOR).
 *
 * El CVV no se pide ni se almacena (guía, sección 10).
 */
class TarjetaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ruta bajo `auth`: siempre opera sobre el usuario autenticado
    }

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
        ];
    }
}
