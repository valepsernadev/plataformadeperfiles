{{--
    Formulario de datos personales y financieros.

    El backend solo acepta los campos con regla de validación
    (PerfilUpdateRequest). `role` no está entre ellos, así que un campo oculto
    o un parámetro añadido a mano con `role=administrador` no tiene efecto.
--}}
<div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
    <div class="max-w-2xl">
        <h3 class="text-lg font-medium text-gray-900">Información personal y financiera</h3>
        <p class="mt-1 text-sm text-gray-600">
            Datos clasificados como confidenciales. Solo tú puedes verlos y modificarlos.
        </p>

        <form method="post" action="{{ route('perfil.update') }}" class="mt-6 space-y-6">
            @csrf
            @method('patch')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <x-input-label for="nombre_completo" value="Nombre completo" />
                    <x-text-input id="nombre_completo" name="nombre_completo" type="text" class="mt-1 block w-full"
                                  :value="old('nombre_completo', $usuario->nombre_completo)" required autocomplete="name" />
                    <x-input-error :messages="$errors->get('nombre_completo')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="numero_identificacion" value="Número de identificación" />
                    <x-text-input id="numero_identificacion" name="numero_identificacion" type="text"
                                  class="mt-1 block w-full"
                                  :value="old('numero_identificacion', $usuario->numero_identificacion)" required />
                    <x-input-error :messages="$errors->get('numero_identificacion')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="telefono" value="Teléfono" />
                    <x-text-input id="telefono" name="telefono" type="text" class="mt-1 block w-full"
                                  :value="old('telefono', $usuario->telefono)" required autocomplete="tel" />
                    <x-input-error :messages="$errors->get('telefono')" class="mt-2" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="email" value="Correo electrónico" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                                  :value="old('email', $usuario->email)" required autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="direccion" value="Dirección" />
                    <x-text-input id="direccion" name="direccion" type="text" class="mt-1 block w-full"
                                  :value="old('direccion', $usuario->direccion)" required autocomplete="street-address" />
                    <x-input-error :messages="$errors->get('direccion')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="ocupacion" value="Ocupación" />
                    <x-text-input id="ocupacion" name="ocupacion" type="text" class="mt-1 block w-full"
                                  :value="old('ocupacion', $usuario->ocupacion)" required />
                    <x-input-error :messages="$errors->get('ocupacion')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="ingresos_mensuales" value="Ingresos mensuales" />
                    <x-text-input id="ingresos_mensuales" name="ingresos_mensuales" type="number" step="0.01" min="0"
                                  class="mt-1 block w-full"
                                  :value="old('ingresos_mensuales', $usuario->ingresos_mensuales)" required />
                    <x-input-error :messages="$errors->get('ingresos_mensuales')" class="mt-2" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="entidad_bancaria" value="Entidad bancaria" />
                    <x-text-input id="entidad_bancaria" name="entidad_bancaria" type="text" class="mt-1 block w-full"
                                  :value="old('entidad_bancaria', $usuario->entidad_bancaria)" required />
                    <x-input-error :messages="$errors->get('entidad_bancaria')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button>Guardar</x-primary-button>
            </div>
        </form>
    </div>
</div>
