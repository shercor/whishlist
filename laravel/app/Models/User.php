<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Las listas de deseos propias.
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Productos que este usuario dio de alta, sean privados o parte del
     * catálogo público.
     */
    public function createdProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'created_by_user_id');
    }

    /**
     * Solicitudes de acceso que hizo a listas privadas de otros.
     */
    public function accessRequests(): HasMany
    {
        return $this->hasMany(WishlistAccess::class);
    }

    /**
     * Regalos que este usuario reservó para otros.
     *
     * Es "lo que yo voy a regalar", nunca "lo que me van a regalar": las
     * reservas sobre las listas propias no se consultan por acá.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
