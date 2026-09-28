<?php

namespace App\Policies;

use App\Models\User;

/**
 * Autorización sobre el recurso `User` — protección contra IDOR horizontal
 * (guía, secciones 4 y 5: "Escalación de privilegios ... o al perfil de otro
 * usuario" y "Falta de validación de que el id editado pertenezca al usuario
 * autenticado").
 *
 * Laravel descubre esta policy automáticamente por convención de nombres
 * (App\Models\User -> App\Policies\UserPolicy), así que no hace falta
 * registrarla en ningún proveedor.
 */
class UserPolicy
{
    /**
     * Ver el perfil. Solo el propio dueño del recurso.
     */
    public function view(User $autenticado, User $recurso): bool
    {
        return $this->esElMismo($autenticado, $recurso);
    }

    /**
     * Modificar el perfil. Solo el propio dueño del recurso.
     *
     * Es la comprobación que impide un IDOR: aunque un atacante consiga que la
     * aplicación resuelva el id de otra persona, esta comparación lo rechaza.
     */
    public function update(User $autenticado, User $recurso): bool
    {
        return $this->esElMismo($autenticado, $recurso);
    }

    /**
     * Listar cuentas. Exclusivo del panel de administración.
     */
    public function viewAny(User $autenticado): bool
    {
        return $autenticado->esAdministrador();
    }

    /**
     * Eliminar cuentas. Un administrador puede eliminar cualquier cuenta; una
     * persona solo la suya (baja voluntaria desde su perfil).
     */
    public function delete(User $autenticado, User $recurso): bool
    {
        return $autenticado->esAdministrador() || $this->esElMismo($autenticado, $recurso);
    }

    private function esElMismo(User $autenticado, User $recurso): bool
    {
        return $autenticado->id === $recurso->id;
    }
}
