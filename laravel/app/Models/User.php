<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

#[Fillable(['name', 'username', 'show_name', 'email', 'password'])]
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
            'show_name' => 'boolean',
        ];
    }

    /**
     * Con qué se le nombra delante de otros.
     *
     * Este método es el único lugar del que debe salir el nombre de una
     * persona hacia una vista. Usar `$user->name` directo se salta la
     * decisión de privacidad y es como se filtra un nombre sin querer.
     */
    public function publicName(): string
    {
        return $this->show_name && $this->name
            ? $this->name
            : $this->handle();
    }

    /**
     * El arroba, siempre. Es lo único que se puede buscar.
     */
    public function handle(): string
    {
        return '@'.$this->username;
    }

    /**
     * Buscar personas.
     *
     * A propósito solo mira `username` y nunca `name`: si buscara por nombre,
     * la opción de ocultarlo no serviría de nada —bastaría con probar nombres
     * hasta que alguien apareciera—.
     */
    public function scopeSearchByUsername(Builder $query, string $term): Builder
    {
        $limpio = Str::of($term)->trim()->ltrim('@')->lower()
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->toString();

        if ($limpio === '') {
            // Sin término no se lista a todo el mundo: el directorio completo
            // de la plataforma no es algo que deba poder recorrerse.
            return $query->whereRaw('1 = 0');
        }

        return $query->where('username', 'like', $limpio.'%');
    }

    /**
     * Las listas de esta persona que el que mira puede alcanzar sin pedir
     * permiso. Nunca revela que existen las privadas.
     */
    public function visibleWishlistsFor(User $viewer): HasMany
    {
        return $this->wishlists()->when(
            $viewer->id !== $this->id,
            fn (Builder $query) => $query->public(),
        );
    }

    /**
     * Palabras que no pueden ser un usuario porque chocarían con una ruta:
     * el perfil vive en /u/{username}, pero estas aparecen sueltas en la raíz
     * o son las que usaría alguien para hacerse pasar por el sistema.
     */
    public const USERNAMES_RESERVADOS = [
        'admin', 'administrador', 'root', 'api', 'login', 'logout', 'register',
        'wishlists', 'discover', 'reservations', 'access', 'products', 'users',
        'perfil', 'profile', 'whishlist', 'soporte', 'ayuda', 'null', 'undefined',
    ];

    /**
     * Las reglas del usuario, en un solo lugar: las comparten el registro y la
     * pantalla de perfil, y si se separan terminan divergiendo.
     */
    public static function usernameRules(?self $ignorar = null): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:30',
            // Empieza por letra y sigue con letras, números o guion bajo. Sin
            // mayúsculas ni tildes: un usuario tiene que poder dictarse por
            // teléfono sin explicar cómo se escribe.
            'regex:/^[a-z][a-z0-9_]*$/',
            Rule::notIn(self::USERNAMES_RESERVADOS),
            Rule::unique('users', 'username')->ignore($ignorar),
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
