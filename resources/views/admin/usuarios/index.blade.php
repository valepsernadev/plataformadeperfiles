<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Administración de usuarios
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @if (session('estado') === 'usuario-eliminado')
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    La cuenta se marcó como eliminada (borrado lógico).
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-4 sm:p-6 border-b border-gray-100">
                    <h3 class="text-lg font-medium text-gray-900">Cuentas registradas</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Este listado muestra únicamente los datos mínimos:
                        <strong>nombre, correo y número de identificación</strong>.
                        Nunca ingresos, entidad bancaria ni datos de tarjeta.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">
                                    Nombre
                                </th>
                                <th scope="col" class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">
                                    Correo
                                </th>
                                <th scope="col" class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">
                                    Identificación
                                </th>
                                <th scope="col" class="px-6 py-3 text-right font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($usuarios as $usuario)
                                <tr>
                                    {{-- Todo va con `{{ }}`, que escapa HTML. Un nombre
                                         como `<script>alert(1)</script>` se muestra como
                                         texto, no se ejecuta (guía, sección 4). --}}
                                    <td class="px-6 py-4 text-gray-900">
                                        {{ $usuario->nombre_completo }}

                                        @if ($usuario->trashed())
                                            <span class="ms-2 rounded-md border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-medium text-red-800">
                                                Eliminado
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-gray-600">{{ $usuario->email }}</td>
                                    <td class="px-6 py-4 font-mono text-gray-600">{{ $usuario->numero_identificacion }}</td>
                                    <td class="px-6 py-4 text-right">
                                        {{-- Una cuenta ya eliminada no admite
                                             ninguna acción. El backend lo
                                             rechaza igualmente, por si la
                                             petición llega sin pasar por aquí. --}}
                                        @if ($usuario->trashed())
                                            <span class="text-xs text-gray-400">Sin acciones</span>
                                        @else
                                            <form method="post"
                                                  action="{{ route('admin.usuarios.destroy', $usuario) }}"
                                                  onsubmit="return confirm('¿Marcar esta cuenta como eliminada?');">
                                                @csrf
                                                @method('delete')

                                                <x-danger-button>
                                                    Eliminar
                                                </x-danger-button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                        No hay cuentas para mostrar.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($usuarios->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $usuarios->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
