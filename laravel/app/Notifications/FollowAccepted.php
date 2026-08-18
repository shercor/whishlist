<?php

namespace App\Notifications;

use App\Models\Follow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A quien pidió seguir: le aceptaron.
 *
 * Importa más de lo que parece: seguir a alguien es el paso previo a que te
 * pueda dar una lista privada, así que hasta que te aceptan no puedes ni pedir
 * nada. Sin aviso, esa espera no tenía final visible.
 */
class FollowAccepted extends Notification implements ShouldQueue
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
        $quien = $this->follow->followed;

        return [
            'titulo' => 'Ya sigues a '.$quien->publicName(),
            'detalle' => 'Aceptó tu solicitud. Ya puedes ver sus listas públicas.',
            'url' => route('users.show', $quien),
            'icono' => '✅',
        ];
    }
}
