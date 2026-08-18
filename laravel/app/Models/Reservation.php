<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'wishlist_item_id',
        'user_id',
        'status',
        'expires_at',
        'expiry_warned_at',
        'released_at',
        'note',
        'active_flag',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'expiry_warned_at' => 'datetime',
            'released_at' => 'datetime',
            'active_flag' => 'integer',
        ];
    }

    public function wishlistItem(): BelongsTo
    {
        return $this->belongsTo(WishlistItem::class);
    }

    /**
     * Quien reservó el regalo. Este dato jamás debe llegar al dueño de la
     * lista a la que pertenece el ítem.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusEnum(): ReservationStatus
    {
        return ReservationStatus::fromLabel($this->status);
    }

    public function isActive(): bool
    {
        return $this->active_flag !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotNull('active_flag');
    }

    /**
     * Reservas vivas cuyo plazo ya pasó, para que un job las libere.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('active_flag')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Reservas vivas a las que les quedan pocos días y a las que todavía no
     * se ha avisado.
     *
     * El «todavía no» lo decide `expiry_warned_at` y no la fecha: el comando
     * corre a diario sobre una ventana de varios días, así que sin esa marca
     * la misma reserva avisaría una vez por día hasta vencer.
     */
    public function scopeExpiringSoon(Builder $query, int $dias): Builder
    {
        return $query->whereNotNull('active_flag')
            ->whereNull('expiry_warned_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays($dias));
    }

    /**
     * Marca la reserva como terminada. Soltar active_flag es lo que libera el
     * ítem: el índice único deja de bloquear una reserva nueva.
     */
    public function release(ReservationStatus $status): bool
    {
        return $this->update([
            'status' => $status->label(),
            'active_flag' => null,
            'released_at' => now(),
        ]);
    }
}
