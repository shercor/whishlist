<?php

namespace App\Policies;

use App\Enums\WishlistVisibility;
use App\Models\User;
use App\Models\Wishlist;

class WishlistPolicy
{
    /**
     * Quién puede abrir la lista. Cuatro caminos y ninguno más:
     *
     * 1. Ser el dueño.
     * 2. Haber llegado con el enlace secreto. Conocer el enlace es el permiso,
     *    y no exige seguir a nadie: es la puerta para la tía que no usa la app.
     * 3. Tener un acceso vivo anotado —te invitaron, lo pediste, o entraste
     *    con el enlace—. Los dos primeros se caen si dejas de seguir al dueño.
     * 4. Que la lista sea pública *y* el perfil del dueño sea alcanzable.
     *
     * El cuarto es el que cambió: una lista pública de un perfil privado ya no
     * la ve cualquiera, solo sus seguidores. Si no fuera así, marcar el perfil
     * como privado no serviría de nada.
     */
    public function view(User $user, Wishlist $wishlist): bool
    {
        // El segundo camino es el único que no vive en la base: es un dato de
        // *esta* sesión. Por eso está aquí y no en viewDurably().
        if ($wishlist->isUnlockedByLink()) {
            return true;
        }

        return $this->viewDurably($user, $wishlist);
    }

    /**
     * Lo mismo que view(), pero sin mirar la sesión.
     *
     * Existe porque `view()` mezcla dos cosas que solo coinciden dentro de una
     * petición: lo que esta persona tiene concedido, y lo que *esta sesión*
     * abrió con el enlace. Preguntar `view()` en nombre de un tercero —como
     * hace el barrido de reservas fuera de alcance— arrastra la sesión de
     * quien pregunta, y entonces una lista que yo abrí con el enlace parece
     * alcanzable para todo el mundo. En consola no hay sesión y no se nota;
     * el día que alguien llame al barrido desde una petición, sí.
     *
     * No pierde a nadie por el camino: entrar con el enlace deja anotado un
     * acceso de origen `enlace` (ver `WishlistController::recordLinkAccess`),
     * que es un hecho guardado y lo ve `hasLiveAccess()`. La sesión solo
     * ahorra volver a pegar el token.
     *
     * Quien decida por un tercero debe preguntar por acá, siempre.
     */
    public function viewDurably(User $user, Wishlist $wishlist): bool
    {
        if ($this->owns($user, $wishlist)) {
            return true;
        }

        if ($this->hasLiveAccess($user, $wishlist)) {
            return true;
        }

        return $wishlist->visibilityEnum() === WishlistVisibility::PUBLIC
            && $this->canReachProfile($user, $wishlist->user);
    }

    /**
     * Un acceso anotado que todavía está en pie.
     *
     * «Aprobado» no basta: el acceso que nació de una invitación o de una
     * solicitud exige que la persona siga al dueño *ahora*, no solo el día que
     * se lo dieron. Sin esta segunda pregunta, dejar de seguir a alguien no le
     * quitaría nada y quedarían accesos sobreviviendo a la relación.
     */
    private function hasLiveAccess(User $user, Wishlist $wishlist): bool
    {
        $acceso = $wishlist->accesses()->approved()->where('user_id', $user->id)->first();

        if (! $acceso) {
            return false;
        }

        if (! $acceso->sourceEnum()->requiresFollow()) {
            return true;
        }

        return $wishlist->user->isFollowedBy($user);
    }

    /**
     * Si el perfil del dueño se deja mirar por esta persona.
     */
    private function canReachProfile(User $user, User $owner): bool
    {
        return ! $owner->is_private || $owner->isFollowedBy($user);
    }

    public function update(User $user, Wishlist $wishlist): bool
    {
        return $this->owns($user, $wishlist);
    }

    public function delete(User $user, Wishlist $wishlist): bool
    {
        return $this->owns($user, $wishlist);
    }

    /**
     * Agregar, editar o borrar regalos de la lista.
     */
    public function manageItems(User $user, Wishlist $wishlist): bool
    {
        return $this->owns($user, $wishlist);
    }

    /**
     * Aprobar o rechazar solicitudes de acceso.
     */
    public function respondAccess(User $user, Wishlist $wishlist): bool
    {
        return $this->owns($user, $wishlist);
    }

    /**
     * Pedir acceso solo tiene sentido sobre la lista privada de otro, y una
     * sola vez: la tabla tiene único (wishlist_id, user_id).
     *
     * Además hay que seguir al dueño. No es un trámite de más: es lo que
     * convierte «cualquiera puede pedirme acceso» en «me lo pide gente a la
     * que ya le abrí la puerta». Y como el dueño acepta o rechaza el
     * seguimiento, controla desde antes quién puede siquiera pedirle algo.
     */
    public function requestAccess(User $user, Wishlist $wishlist): bool
    {
        if ($this->owns($user, $wishlist)) {
            return false;
        }

        if ($wishlist->visibilityEnum()->isReachableWithoutApproval()) {
            return false;
        }

        if (! $wishlist->user->isFollowedBy($user)) {
            return false;
        }

        return ! $wishlist->accesses()->where('user_id', $user->id)->exists();
    }

    /**
     * Ver quién entró a la lista y echar a alguien. Solo el dueño.
     */
    public function manageAccess(User $user, Wishlist $wishlist): bool
    {
        return $this->owns($user, $wishlist);
    }

    private function owns(User $user, Wishlist $wishlist): bool
    {
        return $wishlist->user_id === $user->id;
    }
}
