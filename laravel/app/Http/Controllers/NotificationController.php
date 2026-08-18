<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * La campana: lo que pasó mientras no estabas.
 *
 * Cada persona ve exclusivamente las suyas —la relación `notifications` de
 * Laravel filtra por `notifiable`—, y eso importa más de lo que parece: la
 * notificación de una reserva por vencer cuenta qué regalo reservaste, que es
 * justo lo que el dueño de la lista no puede saber.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notificaciones' => $request->user()
                ->notifications()
                ->latest()
                ->limit(50)
                ->get(),
        ]);
    }

    /**
     * Abrir una notificación: se marca leída y se va a donde apunta.
     *
     * El enlace pasa por acá en vez de ir directo al destino para que leerla
     * y atenderla sean el mismo gesto. Marcarlas a mano es lo que nadie hace,
     * y una campana que siempre muestra un número deja de significar algo.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $notificacion = $request->user()->notifications()->findOrFail($notification);

        $notificacion->markAsRead();

        return redirect($notificacion->data['url'] ?? route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Notificaciones marcadas como leídas.');
    }
}
