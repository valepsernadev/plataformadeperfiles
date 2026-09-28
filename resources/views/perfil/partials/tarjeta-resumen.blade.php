{{--
    Resumen de la tarjeta: SOLO datos enmascarados. El número completo vive
    cifrado en la tabla `tarjetas` y no se descifra para pintar esta tarjeta
    del perfil; se usan `ultimos_4_digitos` y `fecha_vencimiento`, que están en
    claro precisamente para esto.
--}}
<div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
    <div class="max-w-2xl">
        <h3 class="text-lg font-medium text-gray-900">Tarjeta</h3>
        <p class="mt-1 text-sm text-gray-600">
            Dato clasificado como <strong>restringido</strong>. Se guarda cifrado y solo se
            muestra enmascarado.
        </p>

        @if (session('estado') === 'tarjeta-actualizada')
            <div class="mt-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                Tarjeta actualizada correctamente.
            </div>
        @endif

        @if ($tarjeta)
            <dl class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500">Número</dt>
                    <dd class="mt-1 font-mono text-gray-900">{{ $tarjeta->numero_enmascarado }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Vencimiento</dt>
                    <dd class="mt-1 font-mono text-gray-900">{{ $tarjeta->fecha_vencimiento }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Titular</dt>
                    <dd class="mt-1 text-gray-900">{{ $tarjeta->nombre_titular }}</dd>
                </div>
            </dl>
        @else
            <p class="mt-4 text-sm text-gray-600">Todavía no tienes una tarjeta registrada.</p>
        @endif

        <p class="mt-4 text-xs text-gray-500">
            Por seguridad nunca mostramos el número completo ni el código de seguridad (CVV),
            que no almacenamos.
        </p>

        <div class="mt-4">
            <a href="{{ route('perfil.tarjeta.edit') }}"
               class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Actualizar tarjeta
            </a>
        </div>
    </div>
</div>
