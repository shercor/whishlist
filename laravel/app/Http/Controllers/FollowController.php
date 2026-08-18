<?php

namespace App\Http\Controllers;

use App\Enums\FollowStatus;
use App\Models\Follow;
use App\Models\User;
use App\Notifications\FollowAccepted;
use App\Notifications\FollowReceived;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Seguir gente.
 *
 * Un perfil público acepta al instante. Uno privado deja la solicitud
 * pendiente: es lo que le da sentido a marcarlo como privado, porque su dueño
 * decide quién entra antes de que esa persona pueda siquiera pedirle una lista.
 *
 * No hay tarea que limpie accesos cuando alguien deja de seguir: la policy
 * pregunta por el seguimiento cada vez que se abre una lista, así que dejar de
 * seguir corta el acceso en el mismo instante y no hay nada que sincronizar.
 */
class FollowController extends Controller
{
    /**
     * Mi gente: quién me sigue, a quién sigo, y lo que está pendiente en
     * ambos sentidos.
     */
    public function index(Request $request): View
    {
        $usuario = $request->user();

        return view('follows.index', [
            'porResponder' => $usuario->followerLinks()->pending()->with('follower')->latest()->get(),
            'seguidores' => $usuario->followerLinks()->accepted()->with('follower')->latest()->get(),
            'siguiendo' => $usuario->followingLinks()->accepted()->with('followed')->latest()->get(),
            'misPendientes' => $usuario->followingLinks()->pending()->with('followed')->latest()->get(),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $yo = $request->user();

        abort_if($user->id === $yo->id, 403, 'No puedes seguirte a ti mismo.');

        // Un perfil público no tiene nada que aprobar; uno privado sí.
        $estado = $user->followsAreAutoAccepted()
            ? FollowStatus::ACCEPTED
            : FollowStatus::PENDING;

        // null cuando el seguimiento ya existía: es lo que distingue «acabo de
        // seguirte» de un doble clic, y solo el primero se avisa.
        $follow = null;

        try {
            $follow = Follow::create([
                'follower_id' => $yo->id,
                'followed_id' => $user->id,
                'status' => $estado->label(),
                'responded_at' => $estado->isActive() ? now() : null,
            ]);
        } catch (QueryException $e) {
            // 23000 acá solo puede ser el único de (follower_id, followed_id):
            // doble clic. Ya lo sigue o ya lo pidió, que es lo que quería.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }

        if ($follow) {
            $user->notify(new FollowReceived($follow->load('follower')));
        }

        return back()->with('status', $estado->isActive()
            ? "Ahora sigues a {$user->handle()}."
            : "Solicitud enviada a {$user->handle()}. Tiene que aceptarla.");
    }

    /**
     * Dejar de seguir. Se borra la fila en vez de marcarla: volver a seguir
     * después no debería arrastrar el historial de un rechazo viejo.
     *
     * Ojo con el efecto: esto le quita a la persona, en el acto, el acceso a
     * las listas privadas que le habían invitado o que había pedido.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->user()->followingLinks()->where('followed_id', $user->id)->delete();

        return back()->with('status', "Dejaste de seguir a {$user->handle()}.");
    }

    /**
     * El dueño responde una solicitud de seguimiento.
     */
    public function update(Request $request, Follow $follow): RedirectResponse
    {
        abort_if($follow->followed_id !== $request->user()->id, 403);

        $validado = $request->validate([
            'decision' => ['required', Rule::in(['aceptar', 'rechazar'])],
        ]);

        if ($validado['decision'] === 'rechazar') {
            // Rechazar borra: si insiste, que sea una solicitud nueva.
            $follow->delete();

            return back()->with('status', 'Solicitud rechazada.');
        }

        $follow->update([
            'status' => FollowStatus::ACCEPTED->label(),
            'responded_at' => now(),
        ]);

        // Seguir es el paso previo a que te puedan dar una lista privada, así
        // que quien pidió necesita saber que ya puede.
        $follow->follower->notify(new FollowAccepted($follow->fresh()));

        return back()->with('status', "{$follow->follower->handle()} ya te sigue.");
    }

    /**
     * Echar a un seguidor. Igual que dejar de seguir, pero al revés, y con el
     * mismo efecto inmediato sobre lo que esa persona podía ver.
     */
    public function remove(Request $request, User $user): RedirectResponse
    {
        $request->user()->followerLinks()->where('follower_id', $user->id)->delete();

        return back()->with('status', "{$user->handle()} ya no te sigue.");
    }
}
