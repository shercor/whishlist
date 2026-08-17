<?php

namespace App\Models;

use App\Enums\WishlistVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wishlist extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'visibility',
        'share_token',
        'event_date',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(WishlistAccess::class);
    }

    /**
     * El enum detrás de la columna. Se llama distinto que la columna para que
     * no haya dudas de si $wishlist->visibility devuelve string o enum.
     */
    public function visibilityEnum(): WishlistVisibility
    {
        return WishlistVisibility::fromLabel($this->visibility);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', WishlistVisibility::PUBLIC->label());
    }

    /**
     * Clave de sesión donde se anotan las listas abiertas con su enlace.
     */
    private const SESION_ENLACES = 'wishlists_desbloqueadas';

    /**
     * Conocer el enlace secreto es el permiso. Se anota en la sesión para que
     * el invitado pueda navegar y reservar sin volver a pegar el token en cada
     * URL, y para que la policy tenga cómo enterarse.
     */
    public function unlockByLink(): void
    {
        $abiertas = session(self::SESION_ENLACES, []);
        $abiertas[] = $this->id;

        session([self::SESION_ENLACES => array_values(array_unique($abiertas))]);
    }

    public function isUnlockedByLink(): bool
    {
        return in_array($this->id, session(self::SESION_ENLACES, []), true);
    }

    /**
     * Listas que el usuario puede alcanzar sin pedir permiso: las públicas y
     * las suyas propias.
     */
    public function scopeBrowsableBy(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user) {
            $query->where('visibility', WishlistVisibility::PUBLIC->label())
                ->orWhere('user_id', $user->id);
        });
    }
}
