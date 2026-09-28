<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Actualizar tarjeta
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">Datos de la tarjeta</h3>

                <p class="mt-1 text-sm text-gray-600">
                    Este es el único dato clasificado como <strong>restringido</strong> del sistema.
                    Se valida con el algoritmo de Luhn antes de guardarlo y se almacena cifrado.
                </p>

                <p class="mt-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                    <strong>No pedimos el código de seguridad (CVV) ni lo almacenamos.</strong>
                </p>

                @if ($tarjeta)
                    <div class="mt-6 rounded-md bg-gray-50 border border-gray-200 px-4 py-3 text-sm">
                        <span class="text-gray-500">Tarjeta actual:</span>
                        <span class="font-mono text-gray-900">{{ $tarjeta->numero_enmascarado }}</span>
                        <span class="text-gray-500">· vence {{ $tarjeta->fecha_vencimiento }}</span>
                    </div>
                @endif

                <form method="post" action="{{ route('perfil.tarjeta.update') }}" class="mt-6 space-y-6">
                    @csrf
                    @method('put')

                    <div>
                        <x-input-label for="numero_tarjeta" value="Número de tarjeta" />
                        <x-text-input id="numero_tarjeta" name="numero_tarjeta" type="text"
                                      class="mt-1 block w-full" :value="old('numero_tarjeta')"
                                      required inputmode="numeric" autocomplete="cc-number"
                                      placeholder="4111 1111 1111 1111" />
                        <x-input-error :messages="$errors->get('numero_tarjeta')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="fecha_vencimiento" value="Vencimiento (MM/YY)" />
                            <x-text-input id="fecha_vencimiento" name="fecha_vencimiento" type="text"
                                          class="mt-1 block w-full"
                                          :value="old('fecha_vencimiento', $tarjeta?->fecha_vencimiento)"
                                          required placeholder="08/29" autocomplete="cc-exp" />
                            <x-input-error :messages="$errors->get('fecha_vencimiento')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="nombre_titular" value="Nombre del titular" />
                            <x-text-input id="nombre_titular" name="nombre_titular" type="text"
                                          class="mt-1 block w-full"
                                          :value="old('nombre_titular', $tarjeta?->nombre_titular)"
                                          required autocomplete="cc-name" />
                            <x-input-error :messages="$errors->get('nombre_titular')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Guardar tarjeta</x-primary-button>

                        <a href="{{ route('perfil.edit') }}" class="text-sm text-gray-600 underline hover:text-gray-900">
                            Volver a mi perfil
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
