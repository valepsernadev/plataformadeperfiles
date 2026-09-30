<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que un nombre de persona contenga solo lo que puede contener un nombre
 * de persona (guía de seguridad, sección 7: "Validación de entrada ... para
 * prevenir XSS").
 *
 * Este control es la primera de las dos capas frente al XSS almacenado. El
 * escenario que corta es concreto: hasta ahora se podía registrar una cuenta con
 * `nombre_completo = <script>alert(1)</script>` y ese valor viajaba hasta el
 * listado del administrador. El escape de Blade (`{{ }}`) ya lo neutralizaba al
 * pintarlo —esa es la segunda capa, y sigue en pie—, pero es mejor no aceptar
 * nunca el dato que confiar en desarmarlo después.
 *
 * Se acepta:
 *  - letras Unicode, incluidas las tildes y las vocales combinantes (\p{L}\p{M});
 *  - espacios simples entre palabras;
 *  - apóstrofos, tanto el recto (') como el tipográfico (’), para O'Brien;
 *  - guiones, para Jean-Luc o Pérez-Guerrero.
 *
 * Se rechaza todo lo demás: dígitos, etiquetas HTML, arrobas, almohadillas y
 * cualquier otro símbolo. Una dirección, una ocupación o un titular de tarjeta
 * NO usan esta regla, porque son texto libre por naturaleza.
 */
class NombrePropio implements ValidationRule
{
    /**
     * `\A` y `\z` en vez de `^` y `$` porque `$` también hace match antes de un
     * salto de línea final, y aquí no queremos que un "\n" colado cuele.
     *
     * La estructura es "palabra (separador palabra)*", de modo que el nombre no
     * puede empezar ni terminar con un separador, ni tener dos seguidos.
     */
    private const PATRON = "/\A[\p{L}\p{M}]+(?:[ '\x{2019}-][\p{L}\p{M}]+)*\z/u";

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (preg_match(self::PATRON, (string) $value) !== 1) {
            $fail('El :attribute solo puede contener letras, espacios, apóstrofos y guiones.');
        }
    }

    /**
     * Normaliza el valor antes de validarlo: recorta los extremos y colapsa los
     * espacios repetidos.
     *
     * Vive aquí, junto al patrón, y no en cada `FormRequest`, para que la regla
     * y su normalización no se puedan desincronizar. Sin esto, un nombre pegado
     * desde otro sitio con un espacio de más (`"Juan  Pérez"`) sería rechazado
     * con un error confuso, cuando el nombre en realidad está bien escrito.
     *
     * Los dos `FormRequest` que usan la regla (`RegisterRequest` y
     * `PerfilUpdateRequest`) la llaman desde `prepareForValidation()`.
     */
    public static function normalizar(mixed $valor): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $valor));
    }
}
