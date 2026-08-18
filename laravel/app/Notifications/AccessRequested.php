<?php

namespace App\Notifications;

use App\Models\WishlistAccess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Al dueño: alguien pidió ver una de tus listas privadas.
 *
 * Sin esto, una solicitud solo se descubre entrando a «Solicitudes» a
 * propósito, que es justo lo que no se hace cuando uno no sabe que hay algo
 * esperando. El que pide se queda mirando un pedido que quizá nadie vio.
 */
class AccessRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly WishlistAccess $acceso) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $quienPide = $this->acceso->user;

        return [
            // publicName() y no name: quien oculta su nombre tampoco lo filtra
            // por acá. Una notificación es texto que se guarda tal cual, así
            // que un name suelto quedaría escrito para siempre.
            'titulo' => $quienPide->publicName().' quiere ver tu lista',
            'detalle' => $this->acceso->wishlist->name,
            'url' => route('access.index'),
            'icono' => '🔑',
        ];
    }
}
