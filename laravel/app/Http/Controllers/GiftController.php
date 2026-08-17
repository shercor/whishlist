<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * La lista de otra persona, vista por quien le va a regalar algo.
 *
 * Es la contracara de WishlistController: acá sí se consulta el estado de las
 * reservas, pero solo como "tomado / libre". Quién lo tomó no sale de acá,
 * salvo para el propio interesado.
 */
class GiftController extends Controller
{
    public function show(Request $request, Wishlist $wishlist): View
    {
        $user = $request->user();

        // El dueño no tiene nada que hacer en esta pantalla: es justo la que
        // le arruinaría la sorpresa.
        abort_if($wishlist->user_id === $user->id, 403, 'Esta es tu propia lista.');

        // Lista privada ajena: en vez de un 403 seco, la puerta con timbre.
        // A propósito no se muestra el nombre de la lista, solo el de su
        // dueño: si no, bastaría con probar ids para saber qué listas tiene.
        if ($user->cannot('view', $wishlist)) {
            return view('gifts.locked', [
                'wishlist' => $wishlist,
                'yaPidio' => $wishlist->accesses()->where('user_id', $user->id)->exists(),
                // Una lista por enlace no se pide: o tienes el enlace o no.
                'puedePedir' => $user->can('requestAccess', $wishlist),
            ]);
        }

        $items = $wishlist->items()
            ->ordered()
            ->with('product')
            ->withCount([
                // Cuántas reservas vivas tiene: 0 o 1, nunca más.
                'reservations as reserved_count' => fn ($query) => $query->whereNotNull('active_flag'),
                // Y si la que hay es mía, para poder soltarla.
                'reservations as mine_count' => fn ($query) => $query->whereNotNull('active_flag')
                    ->where('user_id', $user->id),
            ])
            ->get();

        return view('gifts.show', compact('wishlist', 'items'));
    }
}
