<?php

use App\Enums\ReservationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Suelta las reservas que quedaron apuntando a un regalo o una lista borrados.
 *
 * A partir de ahora no se producen —los modelos las sueltan al borrar—, pero
 * las que ya existen hay que cerrarlas: mientras sigan vivas, el aviso de
 * vencimiento intenta nombrar un regalo que no está y el job muere en el
 * worker, donde el fallo no lo ve nadie.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('reservations')
            ->whereNotNull('active_flag')
            ->where(function ($query) {
                $query->whereNotIn('wishlist_item_id', function ($sub) {
                    $sub->select('wishlist_items.id')
                        ->from('wishlist_items')
                        ->join('wishlists', 'wishlists.id', '=', 'wishlist_items.wishlist_id')
                        ->whereNull('wishlist_items.deleted_at')
                        ->whereNull('wishlists.deleted_at');
                });
            })
            ->update([
                'status' => ReservationStatus::CANCELLED->label(),
                'active_flag' => null,
                'released_at' => now(),
            ]);
    }

    /**
     * No se revierte: no hay forma de saber cuáles estaban vivas antes, y
     * revivir una reserva sobre un regalo borrado volvería a romper lo mismo.
     */
    public function down(): void {}
};
