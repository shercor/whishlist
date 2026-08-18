<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mis notificaciones.
 *
 * Se buscan siempre dentro de las del usuario autenticado, así que el id de
 * otra persona da 404 y no su contenido.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notificaciones = $request->user()->notifications()->latest()->paginate(30);

        return response()->json([
            'data' => NotificationResource::collection($notificaciones)->resolve($request),
            'meta' => [
                'unread' => $request->user()->unreadNotifications()->count(),
                'total' => $notificaciones->total(),
                'per_page' => $notificaciones->perPage(),
                'current_page' => $notificaciones->currentPage(),
            ],
        ]);
    }

    /**
     * Marcar una como leída. PATCH y no POST: cambia un campo del recurso.
     */
    public function update(Request $request, string $notification): JsonResponse
    {
        $encontrada = $request->user()->notifications()->findOrFail($notification);

        $encontrada->markAsRead();

        return response()->json([
            'data' => NotificationResource::make($encontrada->fresh())->resolve($request),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(status: 204);
    }
}
