<?php

namespace App\Notifications;

use App\Models\Follow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A quien es seguido: alguien te sigue, o quiere seguirte.
 *
 * Una sola clase para los dos casos porque es el mismo hecho contado en dos
 * estados: con perfil público el seguimiento se acepta solo, y con perfil
 * privado queda esperando respuesta. Partirlo en dos notificaciones obligaría
 * a mantener el mismo texto en dos sitios.
 *
 * El seguimiento pendiente ya salía en el contador de «Solicitudes». Lo que no
 * se veía en ninguna parte era el aceptado solo: alguien empezaba a seguirte y
 * no había forma de saberlo.
 */
class FollowReceived extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Si lo que se iba a anunciar ya no existe, el aviso se descarta en vez de
     * fallar.
     *
     * La cola guarda una referencia al modelo y lo vuelve a leer al ejecutar,
     * así que entre encolar y ejecutar cabe que alguien deshaga el seguimiento,
     * borre la lista o suelte la reserva. Sin esto el job muere **dentro del
     * worker**, donde el fallo no lo ve nadie hasta mirar `failed_jobs`.
     * Comprobado: pasaba de verdad.
     *
     * Descartarlo es lo correcto: no hay nada que anunciar.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(private readonly Follow $follow) {}

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
        $quien = $this->follow->follower->publicName();
        $pendiente = ! $this->follow->statusEnum()->isActive();

        return [
            'titulo' => $pendiente
                ? $quien.' quiere seguirte'
                : $quien.' empezó a seguirte',
            'detalle' => $pendiente
                ? 'Aceptarlo le deja ver tus listas públicas.'
                : 'Ya puede ver tus listas públicas.',
            'url' => $pendiente ? route('access.index') : route('follows.index'),
            'icono' => $pendiente ? '👋' : '👤',
        ];
    }
}
