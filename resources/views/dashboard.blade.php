<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Panel
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-lg">
                        Hola, <strong>{{ auth()->user()->nombre_completo }}</strong>.
                    </p>

                    <p class="mt-2 text-sm text-gray-600">
                        Has iniciado sesión con el rol
                        <span class="font-mono">{{ auth()->user()->role }}</span>.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        {{-- El administrador no tiene perfil: su panel sirve
                             solo para administrar usuarios. --}}
                        @unless (auth()->user()->esAdministrador())
                            <a href="{{ route('perfil.edit') }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Mi perfil
                            </a>
                        @endunless

                        @if (auth()->user()->esAdministrador())
                            {{-- Visible solo para administradores, pero la ruta
                                 está protegida en el backend por el middleware
                                 `admin`, no por este @if. --}}
                            <a href="{{ route('admin.usuarios.index') }}"
                               class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Administración de usuarios
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
