<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Panel de administración: listado y eliminación de cuentas.
 *
 * Está detrás del middleware `admin`, así que un usuario con rol `usuario`
 * recibe 403 en el backend aunque escriba la URL a mano. Además cada acción
 * pasa por `UserPolicy`, para no depender de una sola barrera.
 */
class UsuarioController extends Controller
{
    /**
     * Listado de cuentas con **datos mínimos** (guía, sección 7): nombre,
     * correo y cédula. Nunca ingresos, entidad bancaria ni tarjeta.
     *
     * El `select()` explícito es la parte importante: esas columnas no se
     * consultan siquiera, así que no pueden filtrarse por accidente en la
     * vista, en un `toArray()` ni en el HTML renderizado.
     *
     * `withTrashed()` incluye en el listado las cuentas con borrado lógico,
     * para que el administrador siga viendo qué cuentas están eliminadas.
     * `deleted_at` se añade a las columnas porque es lo que permite marcar la
     * fila como "Eliminado" en la vista.
     */
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        $usuarios = User::query()
            ->withTrashed()
            // El listado es de cuentas de usuario: la cuenta administradora no
            // aparece. El filtro va en la consulta, no en la vista.
            ->where('role', 'usuario')
            ->select([...User::columnasListadoAdmin(), 'deleted_at'])
            ->orderBy('nombre_completo')
            ->paginate(15);

        return view('admin.usuarios.index', [
            'usuarios' => $usuarios,
        ]);
    }

    /**
     * Eliminar una cuenta con **soft delete**: la fila se conserva con
     * `deleted_at` marcado, no se borra físicamente (guía, secciones 7 y 9).
     *
     * La ruta se declara con `withTrashed()`, así que aquí llegan también las
     * cuentas ya eliminadas. Eso permite rechazarlas de forma explícita en vez
     * de depender de que la vista no pinte el botón: la barrera está en el
     * backend.
     */
    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        Gate::authorize('delete', $usuario);

        // Una cuenta ya eliminada no admite ninguna acción sobre ella.
        if ($usuario->trashed()) {
            return back()->withErrors([
                'usuario' => 'Esta cuenta ya fue eliminada.',
            ]);
        }

        // Una administradora no puede eliminarse a sí misma desde aquí: evita
        // quedarse fuera del panel por error y dejar el sistema sin acceso
        // administrativo.
        if ($request->user()->id === $usuario->id) {
            return back()->withErrors([
                'usuario' => 'No puedes eliminar tu propia cuenta desde el panel de administración.',
            ]);
        }

        // El listado solo muestra cuentas con rol `usuario`. La ruta rechaza
        // además cualquier intento de eliminar una cuenta administradora
        // enviando su id a mano: la barrera no depende de que la vista no
        // pinte la fila.
        if ($usuario->esAdministrador()) {
            return back()->withErrors([
                'usuario' => 'No se puede eliminar una cuenta de administrador.',
            ]);
        }

        $usuario->delete();

        return redirect()
            ->route('admin.usuarios.index')
            ->with('estado', 'usuario-eliminado');
    }
}
