<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un regalo, servido en json.
 *
 * **Acá vive la regla que sostiene el producto entero, y en la API es más
 * fácil de romper que en las vistas.** En Blade la protección de la sorpresa
 * la daba no consultar las reservas; en json basta un campo de más para
 * publicarlo todo, y no se nota mirando la pantalla.
 *
 * Lo que sale depende de quién pregunta:
 *
 * - **Al dueño de la lista, nada de reservas.** Ni un booleano: saber que un
 *   regalo está tomado ya le dice que alguien se lo va a comprar.
 * - **A cualquier otro**, si está tomado y si lo tomó él mismo. Nunca quién
 *   lo tomó, ni el id de la reserva.
 */
class WishlistItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $comun = [
            'id' => $this->id,
            'name' => $this->displayName(),
            'notes' => $this->notes,
            'priority' => $this->priorityEnum()->label(),
            'position' => $this->position,
            'received' => $this->isReceived(),
            'product' => ProductResource::make($this->whenLoaded('product')),
        ];

        if ($this->debeEsconderReservas($request)) {
            return $comun;
        }

        return [
            ...$comun,
            'is_reserved' => $this->reservedForThisViewer(),
            'reserved_by_me' => $this->reservedByViewer($request),
        ];
    }

    /**
     * Si hay que callar el estado de la reserva.
     *
     * La pregunta está planteada al revés a propósito: **se esconde salvo que
     * se pueda demostrar que quien mira no es el dueño**. Hacen falta las dos
     * cosas —saber de quién es la lista y saber quién pregunta—, y si falta
     * cualquiera de las dos, se calla.
     *
     * Escrito de la otra forma («muestro salvo que sea el dueño») esto ya tuvo
     * un agujero: sin usuario autenticado, un visitante anónimo no era el
     * dueño, así que la reserva salía. Hoy todas las rutas de la API exigen
     * token y no era alcanzable, pero la seguridad de este campo no puede
     * depender de que eso siga siendo cierto mañana.
     *
     * De los dos errores posibles, mostrar de menos se arregla con un reporte
     * de bug; mostrar de más arruina un regalo y no se deshace.
     */
    private function debeEsconderReservas(Request $request): bool
    {
        $duenio = $this->wishlist?->user_id;
        $quienMira = $request->user()?->id;

        return $duenio === null
            || $quienMira === null
            || $duenio === $quienMira;
    }

    /**
     * Aprovecha el `withCount` del controlador si está, y si no pregunta.
     * Sin este respaldo, un endpoint que olvidara el withCount devolvería
     * «libre» para todo y dos personas comprarían el mismo regalo.
     */
    private function reservedForThisViewer(): bool
    {
        if (isset($this->reserved_count)) {
            return $this->reserved_count > 0;
        }

        return $this->isReservedForViewer();
    }

    private function reservedByViewer(Request $request): bool
    {
        if (isset($this->mine_count)) {
            return $this->mine_count > 0;
        }

        return $this->reservations()
            ->whereNotNull('active_flag')
            ->where('user_id', $request->user()?->id)
            ->exists();
    }
}
