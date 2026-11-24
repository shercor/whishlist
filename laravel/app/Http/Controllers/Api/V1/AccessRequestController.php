<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AccessRequestStatus;
use App\Enums\AccessSource;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\WishlistAccessResource;
use App\Http\Resources\WishlistResource;
use App\Models\Wishlist;
use App\Models\WishlistAccess;
use App\Notifications\AccessAnswered;
use App\Notifications\AccessRequested;
use App\Notifications\WishlistShared;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * El reparto de acceso a listas privadas.
 *
 * Dos caminos, como en la web: se pide (y el dueño responde) o el dueño invita
 * a alguien de su gente. Invitar exige que la persona ya te siga, y eso no es
 * un detalle de formulario: sin esa condición se podría repartir una lista
 * privada entre desconocidos mandando ids.
 */
class AccessRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        return response()->json([
            'data' => [
                'received' => WishlistAccessResource::collection(
                    WishlistAccess::query()
                        ->whereIn('wishlist_id', $usuario->wishlists()->select('id'))
                        ->with(['user', 'wishlist'])
                        ->latest()
                        ->get()
                )->resolve($request),
                // De una lista que no te dieron solo se dice de quién es, igual
                // que en la web: su nombre es cosa de su dueño.
                'sent' => $usuario->accessRequests()->with('wishlist.user')->latest()->get()
                    ->map(fn ($acceso) => [
                        ...WishlistAccessResource::make($acceso)->resolve($request),
                        'wishlist' => $acceso->statusEnum()->grantsAccess()
                            ? WishlistResource::make($acceso->wishlist)->resolve($request)
                            : [
                                'id' => $acceso->wishlist->id,
                                'owner' => UserResource::make($acceso->wishlist->user)->resolve($request),
                            ],
                    ]),
            ],
        ]);
    }

    /**
     * Pedir una lista privada.
     */
    public function store(Request $request, Wishlist $wishlist): JsonResponse
    {
        $this->authorize('requestAccess', $wishlist);

        $datos = $request->validate([
            'message' => ['nullable', 'string', 'max:300'],
        ], [], ['message' => 'mensaje']);

        // Si ya pidió antes, no se duplica: el único de (wishlist_id, user_id)
        // lo impide, y volver a pedir es reactivar la misma solicitud.
        $acceso = $wishlist->accesses()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'status' => AccessRequestStatus::PENDING->label(),
                'source' => AccessSource::REQUEST->label(),
                'message' => $datos['message'] ?? null,
                'responded_at' => null,
            ]
        );

        $wishlist->user->notify(new AccessRequested($acceso));

        return response()->json(
            ['data' => WishlistAccessResource::make($acceso->load('wishlist'))->resolve($request)],
            201
        );
    }

    /**
     * El dueño responde: aprobar, rechazar o revocar lo que ya dio.
     */
    public function update(Request $request, WishlistAccess $access): JsonResponse
    {
        $this->authorize('respondAccess', $access->wishlist);

        $datos = $request->validate([
            'status' => ['required', Rule::in([
                AccessRequestStatus::APPROVED->label(),
                AccessRequestStatus::REJECTED->label(),
                AccessRequestStatus::REVOKED->label(),
            ])],
        ]);

        $access->update([
            'status' => $datos['status'],
            'responded_at' => now(),
        ]);

        // Mismo criterio que la web: se avisa de la respuesta a un pedido, y
        // nunca de una revocación.
        if ($access->sourceEnum() === AccessSource::REQUEST
            && $datos['status'] !== AccessRequestStatus::REVOKED->label()) {
            $access->user->notify(new AccessAnswered($access->fresh()));
        }

        return response()->json([
            'data' => WishlistAccessResource::make($access->fresh()->load('user'))->resolve($request),
        ]);
    }

    /**
     * El dueño le da la lista a alguien de su gente, sin que se la pida.
     */
    public function invite(Request $request, Wishlist $wishlist): JsonResponse
    {
        $this->authorize('manageAccess', $wishlist);

        // Por usuario y no por id: la API nunca entrega ids de personas.
        $datos = $request->validate([
            'username' => ['required', 'string'],
        ], [], ['username' => 'persona']);

        // Solo un seguidor aceptado: si no, bastaría con nombrar a cualquiera
        // para colarlo en la lista.
        $invitado = $request->user()->followers()
            ->where('username', $datos['username'])
            ->first();

        if ($invitado === null) {
            throw ValidationException::withMessages([
                'username' => 'Solo puedes darle una lista privada a alguien que te sigue.',
            ]);
        }

        $acceso = $wishlist->accesses()->updateOrCreate(
            ['user_id' => $invitado->id],
            [
                'status' => AccessRequestStatus::APPROVED->label(),
                'source' => AccessSource::INVITATION->label(),
                'responded_at' => now(),
            ]
        );

        $acceso->user->notify(new WishlistShared($acceso));

        return response()->json(
            ['data' => WishlistAccessResource::make($acceso->load('user'))->resolve($request)],
            201
        );
    }

    /**
     * Echar a alguien de una lista.
     *
     * Se borra la fila y no se marca revocada, igual que en la web: el único de
     * (wishlist_id, user_id) dejaría a esa persona bloqueada para siempre si se
     * quisiera volver a invitarla.
     */
    public function revoke(Wishlist $wishlist, WishlistAccess $access): JsonResponse
    {
        $this->authorize('manageAccess', $wishlist);

        abort_if($access->wishlist_id !== $wishlist->id, 404);

        $access->delete();

        return response()->json(status: 204);
    }
}
