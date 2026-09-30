<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifica el captcha de Google reCAPTCHA v2 contra el endpoint `siteverify`
 * (guía de seguridad, sección 4: "suplantación de identidad"; sección 5:
 * ataques automatizados).
 *
 * Dos advertencias que conviene tener presentes y que están repetidas en
 * `docs/documentacion-tecnica.md`:
 *
 * 1. **Con las claves de prueba de Google este control no frena ningún bot.**
 *    Las claves de prueba que trae `.env.example` hacen que Google responda
 *    siempre `success: true`, así que sirven para demostrar el flujo completo
 *    sin crear una cuenta, pero no protegen de nada. Poner las claves reales del
 *    despliegue es cambiar dos variables del `.env`, sin tocar código.
 *
 * 2. **Falla en cerrado.** Si Google no responde (sin internet, timeout, DNS),
 *    se rechaza la petición. Es la decisión coherente con el resto del proyecto
 *    —anteponer el control a la disponibilidad—, pero implica que sin salida a
 *    internet nadie puede registrarse ni entrar. Es intencional, no un descuido.
 *
 * La propiedad `$implicit` de abajo es un detalle fácil de pasar por alto y sin
 * ella el control no sirve de nada: Laravel NO ejecuta las reglas de un campo
 * que no viene en la petición, salvo que la regla sea implícita. Un POST sin
 * `g-recaptcha-response` —justo el caso del bot que no resuelve el captcha— se
 * saltaría esta regla entera y pasaría la validación. La regla `required` es
 * implícita de serie; como aquí la presencia se comprueba dentro de la propia
 * regla, hay que declararlo a mano.
 *
 * Se declara como propiedad y no implementando `ImplicitRule` porque en Laravel
 * 13 esa interfaz está obsoleta y todavía extiende el antiguo contrato `Rule`,
 * que obliga a implementar `passes()` y `message()`. Lo que lee el framework es
 * `$invokable->implicit` (ver InvokableValidationRule::make()).
 */
class Recaptcha implements ValidationRule
{
    /**
     * Hace que la regla se evalúe aunque el campo no venga en la petición.
     *
     * @var bool
     */
    public $implicit = true;

    private const URL_VERIFICACION = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * Segundos de espera antes de considerar que Google no respondió.
     */
    private const TIMEOUT = 10;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // En el entorno de pruebas no se llama a Google. Sin este corte habría
        // que sembrar un token de captcha en cada una de las ~45 peticiones a
        // /login y /register que ya existen en la suite, y la suite entera
        // dependería de tener internet. El control se sigue probando de verdad
        // en CaptchaTest, que fuerza el entorno a `local` igual que hace
        // CsrfTest con el token CSRF.
        if (app()->environment('testing')) {
            return;
        }

        // La presencia del campo se comprueba AQUÍ y no con la regla `required`
        // a propósito: así el corte del entorno de pruebas de arriba cubre de
        // una vez las dos mitades del control (que venga y que sea válido). Si
        // se usara `required`, seguiría exigiéndose el campo en las pruebas y
        // habría que tocar las ~45 peticiones existentes una por una.
        if (blank($value)) {
            $fail('Resuelve la verificación anti-robots para continuar.');

            return;
        }

        $secreto = config('services.recaptcha.secret_key');

        if (blank($secreto)) {
            $fail('La verificación anti-robots no está configurada.');

            return;
        }

        try {
            $respuesta = Http::asForm()
                ->timeout(self::TIMEOUT)
                ->post(self::URL_VERIFICACION, [
                    'secret' => $secreto,
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);
        } catch (Throwable) {
            // Google inalcanzable: se rechaza (ver nota 2 del docblock).
            $fail('No pudimos verificar que no eres un robot. Inténtalo de nuevo.');

            return;
        }

        // `success` viene como booleano en el JSON de Google. La comparación
        // estricta evita que un valor raro (la cadena "true", o su ausencia) se
        // interprete como una validación correcta.
        if ($respuesta->json('success') !== true) {
            $fail('No pudimos verificar que no eres un robot. Inténtalo de nuevo.');
        }
    }
}
