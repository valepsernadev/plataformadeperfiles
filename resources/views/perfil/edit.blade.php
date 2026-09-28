<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mi perfil
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('estado') === 'perfil-actualizado')
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    Perfil actualizado correctamente.
                </div>
            @endif

            @include('perfil.partials.datos')

            @include('perfil.partials.tarjeta-resumen')

            @include('perfil.partials.password')

            @include('perfil.partials.eliminar-cuenta')
        </div>
    </div>
</x-app-layout>
