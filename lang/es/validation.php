<?php

/*
|--------------------------------------------------------------------------
| Mensajes de validación en español
|--------------------------------------------------------------------------
|
| Este archivo es PARCIAL a propósito: solo define las claves que la
| aplicación usa de verdad. Laravel resuelve clave por clave, así que
| cualquier mensaje que falte aquí cae al idioma de respaldo
| (APP_FALLBACK_LOCALE=en) en vez de mostrarse vacío.
|
| Además de ser más claro para quien usa la aplicación, un mensaje de
| validación en el idioma correcto evita que alguien "adivine" reglas
| internas por el texto en inglés.
|
*/

return [

    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña no es correcta.',
    'email' => 'El campo :attribute debe ser una dirección de correo válida.',
    'lowercase' => 'El campo :attribute debe escribirse en minúsculas.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'regex' => 'El formato del campo :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',

    'max' => [
        'array' => 'El campo :attribute no debe tener más de :max elementos.',
        'file' => 'El archivo :attribute no debe pesar más de :max kilobytes.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe superar los :max caracteres.',
    ],

    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El archivo :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],

    'unique' => 'El valor del campo :attribute ya está en uso.',

    'password' => [
        'letters' => 'La :attribute debe contener al menos una letra.',
        'mixed' => 'La :attribute debe contener al menos una mayúscula y una minúscula.',
        'numbers' => 'La :attribute debe contener al menos un número.',
        'symbols' => 'La :attribute debe contener al menos un símbolo.',
        'uncompromised' => 'La :attribute indicada apareció en una filtración de datos. Elige otra distinta.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Nombres legibles de los campos
    |--------------------------------------------------------------------------
    |
    | Así los mensajes dicen "El campo número de identificación es obligatorio"
    | en vez de "El campo numero_identificacion es obligatorio", sin tener que
    | repetir el nombre en cada regla.
    |
    */

    'attributes' => [
        'nombre_completo' => 'nombre completo',
        'numero_identificacion' => 'número de identificación',
        'email' => 'correo',
        'password' => 'contraseña',
        'password_confirmation' => 'confirmación de contraseña',
        'telefono' => 'teléfono',
        'direccion' => 'dirección',
        'ocupacion' => 'ocupación',
        'ingresos_mensuales' => 'ingresos mensuales',
        'entidad_bancaria' => 'entidad bancaria',
        'numero_tarjeta' => 'número de tarjeta',
        'fecha_vencimiento' => 'fecha de vencimiento',
        'nombre_titular' => 'nombre del titular',
    ],

];
