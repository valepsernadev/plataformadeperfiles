<div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
    <div class="max-w-xl">
        <h3 class="text-lg font-medium text-gray-900">Contraseña</h3>
        <p class="mt-1 text-sm text-gray-600">
            Se guarda como hash bcrypt, nunca en texto plano ni cifrada de forma reversible.
        </p>

        <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
            @csrf
            @method('put')

            <div>
                <x-input-label for="update_password_current_password" value="Contraseña actual" />
                <x-text-input id="update_password_current_password" name="current_password" type="password"
                              class="mt-1 block w-full" autocomplete="current-password" />
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password_password" value="Nueva contraseña" />
                <x-text-input id="update_password_password" name="password" type="password"
                              class="mt-1 block w-full" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password_password_confirmation" value="Confirmar contraseña" />
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password"
                              class="mt-1 block w-full" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button>Guardar</x-primary-button>

                @if (session('status') === 'password-updated')
                    <p x-data="{ show: true }" x-show="show" x-transition
                       x-init="setTimeout(() => show = false, 3000)"
                       class="text-sm text-gray-600">Contraseña actualizada.</p>
                @endif
            </div>
        </form>
    </div>
</div>
