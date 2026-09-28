<div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
    <div class="max-w-xl">
        <h3 class="text-lg font-medium text-gray-900">Eliminar cuenta</h3>
        <p class="mt-1 text-sm text-gray-600">
            La cuenta se marca como eliminada (borrado lógico): la fila se conserva con la marca
            <code class="text-xs">deleted_at</code> y deja de ser accesible, pero no se borra
            físicamente de la base de datos.
        </p>

        <x-danger-button
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirmar-eliminacion-cuenta')"
            class="mt-4">
            Eliminar mi cuenta
        </x-danger-button>
    </div>

    <x-modal name="confirmar-eliminacion-cuenta" :show="$errors->eliminarCuenta->isNotEmpty()" focusable>
        <form method="post" action="{{ route('perfil.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-gray-900">
                ¿Seguro que quieres eliminar tu cuenta?
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Esta acción marca la cuenta como eliminada y cierra tu sesión. Introduce tu
                contraseña para confirmar.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="Contraseña" class="sr-only" />

                <x-text-input id="password" name="password" type="password" class="mt-1 block w-3/4"
                              placeholder="Contraseña" />

                <x-input-error :messages="$errors->eliminarCuenta->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    Cancelar
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    Eliminar cuenta
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</div>
