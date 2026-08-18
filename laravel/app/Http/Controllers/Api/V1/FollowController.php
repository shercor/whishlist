<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FollowStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FollowResource;
use App\Models\Follow;
use App\Models\User;
use App\Notifications\FollowAccepted;
use App\Notifications\FollowReceived;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Seguir gente.
 *
 * Mismas reglas que la web, que no son opcionales: un perfil público acepta al
 * instante y uno privado deja la solicitud pendiente. Y dejar de seguir le
 * quita a la persona, en el acto, las listas privadas que tenía por invitación
 * o por solicitud —eso lo sostiene la policy en cada consulta, no hay nada que
 * sincronizar acá—.
 */
class FollowController extends Controller
{
    /**
     * Mi gente, en los cuatro estados que importan.
     */
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        return response()->json([
            'data' => [
                'pending_for_me' => FollowResource::collection(
                    $usuario->followerLinks()->pending()->with('follower')->latest()->get()
                )->resolve($request),
                'followers' => FollowResource::collection(
                    $usuario->followerLinks()->accepted()->with('follower')->latest()->get()
                )->resolve($request),
                'following' => FollowResource::collection(
                    $usuario->followingLinks()->accepted()->with('followed')->latest()->get()
                )->resolve($request),
                'my_pending' => FollowResource::collection(
                    $usuario->followingLinks()->pending()->with('followed')->latest()->get()
                )->resolve($request),
            ],
        ]);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        $yo = $request->user();

        abort_if($user->id === $yo->id, 403, 'No puedes seguirte a ti mismo.');

        $estado = $user->followsAreAutoAccepted()
            ? FollowStatus::ACCEPTED
            : FollowStatus::PENDING;

        // null si el seguimiento ya existía: solo se avisa del que nace.
        $nuevo = null;

        try {
            $follow = $nuevo = Follow::create([
                'follower_id' => $yo->id,
                'followed_id' => $user->id,
                'status' => $estado->label(),
                'responded_at' => $estado->isActive() ? now() : null,
            ]);
        } catch (QueryException $e) {
            // 23000 acá solo puede ser el único de (follower_id, followed_id):
            // ya lo sigue o ya lo pidió, que es el estado que quería.
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $follow = $yo->followTo($user);
        }

        if ($nuevo) {
            $user->notify(new FollowReceived($nuevo->load('follower')));
        }

        return response()->json(
            ['data' => FollowResource::make($follow->load('followed'))->resolve($request)],
            201
        );
    }

    /**
     * Responder una solicitud que me hicieron.
     */
    public function update(Request $request, Follow $follow): JsonResponse
    {
        abort_if($follow->followed_id !== $request->user()->id, 403);

        $validado = $request->validate([
            'decision' => ['required', Rule::in(['aceptar', 'rechazar'])],
        ]);

        if ($validado['decision'] === 'rechazar') {
            // Rechazar borra: si insiste, que sea una solicitud nueva y no el
            // historial de un rechazo viejo.
            $follow->delete();

            return response()->json(status: 204);
        }

        $follow->update([
            'status' => FollowStatus::ACCEPTED->label(),
            'responded_at' => now(),
        ]);

        $follow->follower->notify(new FollowAccepted($follow->fresh()->load('followed')));

        return response()->json([
            'data' => FollowResource::make($follow->fresh()->load('follower'))->resolve($request),
        ]);
    }

    /**
     * Dejar de seguir a alguien.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $request->user()->followingLinks()->where('followed_id', $user->id)->delete();

        return response()->json(status: 204);
    }

    /**
     * Echar a un seguidor: lo mismo al revés, con el mismo efecto inmediato
     * sobre lo que esa persona podía ver.
     */
    public function removeFollower(Request $request, User $user): JsonResponse
    {
        $request->user()->followerLinks()->where('follower_id', $user->id)->delete();

        return response()->json(status: 204);
    }
}
