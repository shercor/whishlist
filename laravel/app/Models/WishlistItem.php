<?php

namespace App\Models;

use App\Enums\ItemPriority;
use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class WishlistItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'wishlist_id',
        'product_id',
        'alias',
        'notes',
        'priority',
        'position',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * OJO: nunca cargar esta relación en una respuesta que vaya a ver el dueño
     * de la lista. Toda la sorpresa depende de eso.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * La reserva viva, si la hay. Misma advertencia que reservations().
     */
    public function activeReservation(): HasOne
    {
        return $this->hasOne(Reservation::class)->whereNotNull('active_flag');
    }

    public function priorityEnum(): ItemPriority
    {
        return ItemPriority::fromLabel($this->priority);
    }

    /**
     * Cómo se muestra el ítem: el alias del dueño manda sobre el nombre del
     * catálogo.
     */
    public function displayName(): string
    {
        return $this->alias ?? $this->product->name;
    }

    public function isReceived(): bool
    {
        return $this->received_at !== null;
    }

    /**
     * Lo que todavía se le puede regalar: ni recibido ni reservado.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('received_at')
            ->whereDoesntHave('reservations', function (Builder $query) {
                $query->whereNotNull('active_flag');
            });
    }

    /**
     * Lo que el dueño ya marcó como recibido y deja de ofrecerse.
     */
    public function scopeReceived(Builder $query): Builder
    {
        return $query->whereNotNull('received_at');
    }

    /**
     * Orden natural de una lista: primero lo manualmente ordenado, luego lo
     * que más quiere el dueño.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')
            ->orderByRaw('FIELD(priority, ?, ?, ?)', [
                ItemPriority::HIGH->label(),
                ItemPriority::MEDIUM->label(),
                ItemPriority::LOW->label(),
            ]);
    }

    /**
     * Estado de reserva pensado para quien mira la lista de otro. Devuelve
     * solo si está tomado o no, nunca por quién.
     */
    public function isReservedForViewer(): bool
    {
        return $this->reservations()
            ->whereNotNull('active_flag')
            ->where('status', ReservationStatus::ACTIVE->label())
            ->exists();
    }
}
