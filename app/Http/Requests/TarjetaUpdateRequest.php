<?php

namespace App\Http\Requests;

use App\Rules\Luhn;
use App\Rules\NombrePropio;
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
 *
 * `nombre_titular` lleva la misma regla que el nombre de la cuenta: este es el
 * segundo sitio donde se escribe, así que restringirlo solo en el registro
 * habría dejado la puerta de atrás de siempre —registrarse con un titular
 * limpio y cambiarlo acto seguido desde "Actualizar tarjeta"—.
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

        // Misma normalización que el registro, para que un espacio de más no
        // provoque un rechazo sorprendente al guardar la tarjeta.
        if ($this->has('nombre_titular')) {
            $this->merge([
                'nombre_titular' => NombrePropio::normalizar($this->input('nombre_titular')),
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
            'nombre_titular' => ['required', 'string', 'max:150', new NombrePropio],
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
