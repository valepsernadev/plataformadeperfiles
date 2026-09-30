{{--
    Widget de Google reCAPTCHA v2, compartido por el registro y el inicio de
    sesión. Ver app/Rules/Recaptcha.php para la parte del servidor.

    Dos detalles deliberados:

    1. El mensaje de error se pinta AQUÍ, a mano. Las vistas de autenticación no
       tienen un bloque de errores global (solo `x-input-error` campo por campo),
       así que un fallo del captcha no se vería en ninguna parte y el formulario
       parecería "no hacer nada" al enviarlo.

    2. El <script> va por el stack `scripts` del layout guest en vez de ir aquí
       dentro, para no dejar una etiqueta <script> en mitad del formulario.

    Si `RECAPTCHA_SITE_KEY` está vacía el widget no se dibuja y el formulario
    será imposible de enviar (el servidor exige el campo). Es preferible que se
    vea el aviso a que falle en silencio.
--}}

@if (filled(config('services.recaptcha.site_key')))
    <div class="mt-6">
        <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>

        <x-input-error :messages="$errors->get('g-recaptcha-response')" class="mt-2" />
    </div>
@else
    <p class="mt-6 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-800">
        El captcha no está configurado: falta <code>RECAPTCHA_SITE_KEY</code> en el <code>.env</code>.
        Mientras tanto no se puede registrar ni iniciar sesión.
    </p>
@endif

@once
    @push('scripts')
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endpush
@endonce
