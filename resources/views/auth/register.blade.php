<x-guest-layout ancho="sm:max-w-3xl">
    <h1 class="text-lg font-semibold text-gray-900">Crear una cuenta</h1>
    <p class="mt-1 text-sm text-gray-600">
        Los campos marcados con <span class="text-red-600">*</span> son obligatorios.
    </p>

    <form method="POST" action="{{ route('register') }}" class="mt-6">
        {{-- Laravel inyecta aquí el token CSRF y rechaza con 419 cualquier POST
             que no lo traiga o que venga de otro sitio (guía, sección 7). --}}
        @csrf

        {{-- ============================ CUENTA ============================ --}}
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Datos de acceso</h2>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <x-input-label for="email" value="Correo electrónico *" />
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                              :value="old('email')" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" value="Contraseña *" />
                <x-text-input id="password" class="block mt-1 w-full" type="password" name="password"
                              required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" value="Confirmar contraseña *" />
                <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password"
                              name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        {{-- ======================= DATOS PERSONALES ======================= --}}
        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-gray-500">Datos personales</h2>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <x-input-label for="nombre_completo" value="Nombre completo *" />
                <x-text-input id="nombre_completo" class="block mt-1 w-full" type="text" name="nombre_completo"
                              :value="old('nombre_completo')" required autocomplete="name" />
                <x-input-error :messages="$errors->get('nombre_completo')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="numero_identificacion" value="Número de identificación *" />
                <x-text-input id="numero_identificacion" class="block mt-1 w-full" type="text"
                              name="numero_identificacion" :value="old('numero_identificacion')" required />
                <x-input-error :messages="$errors->get('numero_identificacion')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="telefono" value="Teléfono *" />
                <x-text-input id="telefono" class="block mt-1 w-full" type="text" name="telefono"
                              :value="old('telefono')" required autocomplete="tel" />
                <x-input-error :messages="$errors->get('telefono')" class="mt-2" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label for="direccion" value="Dirección *" />
                <x-text-input id="direccion" class="block mt-1 w-full" type="text" name="direccion"
                              :value="old('direccion')" required autocomplete="street-address" />
                <x-input-error :messages="$errors->get('direccion')" class="mt-2" />
            </div>
        </div>

        {{-- ======================= DATOS FINANCIEROS ======================= --}}
        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-gray-500">Información financiera</h2>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <x-input-label for="ocupacion" value="Ocupación *" />
                <x-text-input id="ocupacion" class="block mt-1 w-full" type="text" name="ocupacion"
                              :value="old('ocupacion')" required />
                <x-input-error :messages="$errors->get('ocupacion')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="ingresos_mensuales" value="Ingresos mensuales *" />
                <x-text-input id="ingresos_mensuales" class="block mt-1 w-full" type="number" step="0.01"
                              min="0" name="ingresos_mensuales" :value="old('ingresos_mensuales')" required />
                <x-input-error :messages="$errors->get('ingresos_mensuales')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="entidad_bancaria" value="Entidad bancaria *" />
                <x-text-input id="entidad_bancaria" class="block mt-1 w-full" type="text" name="entidad_bancaria"
                              :value="old('entidad_bancaria')" required />
                <x-input-error :messages="$errors->get('entidad_bancaria')" class="mt-2" />
            </div>
        </div>

        {{-- ============================ TARJETA ============================ --}}
        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-gray-500">Datos de la tarjeta</h2>

        <p class="mt-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
            <strong>No pedimos el código de seguridad (CVV) y no lo almacenamos.</strong>
            El número de la tarjeta se guarda cifrado y solo se muestra enmascarado.
        </p>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <x-input-label for="numero_tarjeta" value="Número de tarjeta *" />
                <x-text-input id="numero_tarjeta" class="block mt-1 w-full" type="text" name="numero_tarjeta"
                              :value="old('numero_tarjeta')" required inputmode="numeric"
                              autocomplete="cc-number" placeholder="4111 1111 1111 1111" />
                <p class="mt-1 text-xs text-gray-500">
                    Se valida el dígito de control con el algoritmo de Luhn antes de guardarla.
                </p>
                <x-input-error :messages="$errors->get('numero_tarjeta')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="fecha_vencimiento" value="Vencimiento (MM/YY) *" />
                <x-text-input id="fecha_vencimiento" class="block mt-1 w-full" type="text"
                              name="fecha_vencimiento" :value="old('fecha_vencimiento')" required
                              placeholder="08/29" autocomplete="cc-exp" />
                <x-input-error :messages="$errors->get('fecha_vencimiento')" class="mt-2" />
            </div>

            <div class="sm:col-span-3">
                <x-input-label for="nombre_titular" value="Nombre del titular *" />
                <x-text-input id="nombre_titular" class="block mt-1 w-full" type="text" name="nombre_titular"
                              :value="old('nombre_titular')" required autocomplete="cc-name" />
                <p class="mt-1 text-xs text-gray-500">Puede ser distinto del nombre de la cuenta.</p>
                <x-input-error :messages="$errors->get('nombre_titular')" class="mt-2" />
            </div>
        </div>

        {{-- Captcha: el envío no se acepta sin resolverlo (guía, secciones 4 y 5). --}}
        <x-recaptcha />

        <div class="flex items-center justify-end mt-8">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
               href="{{ route('login') }}">
                ¿Ya tienes cuenta?
            </a>

            <x-primary-button class="ms-4">
                Registrarse
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
