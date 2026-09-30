<?php

namespace App\Http\Requests;

use App\Rules\NombrePropio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Actualización del perfil: información personal y financiera.
 *
 * `role` no aparece en las reglas ni se escribe en el controlador, así que
 * enviar `role=administrador` en el PATCH no cambia nada (escalada de
 * privilegios vertical bloqueada). Tampoco se acepta ningún campo de tarjeta:
 * la tarjeta se actualiza por su propio endpoint, contra su propia tabla.
 *
 * `nombre_completo` lleva la MISMA regla que en el registro. Es importante que
 * sea así: endurecer solo el registro dejaría la puerta de atrás obvia —
 * registrarse con un nombre limpio y acto seguido editar el perfil para poner
 * `<script>alert(1)</script>`.
 */
class PerfilUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización real la hace UserPolicy en el controlador
    }

    /**
     * Normaliza el nombre igual que el registro, para que un espacio de más no
     * provoque un rechazo sorprendente al guardar el perfil.
     */
    protected function prepareForValidation(): void
    {
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
        $usuarioId = $this->user()->id;

        return [
            'nombre_completo' => ['required', 'string', 'max:150', new NombrePropio],
            'numero_identificacion' => [
                'required', 'string', 'max:20',
                Rule::unique('usuarios', 'numero_identificacion')->ignore($usuarioId),
            ],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:150',
                Rule::unique('usuarios', 'email')->ignore($usuarioId),
            ],
            'telefono' => ['required', 'string', 'max:20'],
            'direccion' => ['required', 'string', 'max:255'],
            'ocupacion' => ['required', 'string', 'max:100'],
            'ingresos_mensuales' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'entidad_bancaria' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ese correo ya está registrado por otra cuenta.',
            'numero_identificacion.unique' => 'Ese número de identificación ya está registrado por otra cuenta.',
        ];
    }
}
