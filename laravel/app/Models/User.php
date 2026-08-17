<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\FollowStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

#[Fillable(['name', 'username', 'show_name', 'is_private', 'avatar_path', 'email', 'password'])]
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
            'is_private' => 'boolean',
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
     * La foto de perfil, o null si no puso ninguna.
     */
    public function avatarSrc(): ?string
    {
        return $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null;
    }

    /**
     * Las letras del placeholder cuando no hay foto.
     *
     * Salen de publicName() y no de `name`: si la persona oculta su nombre, sus
     * iniciales reales tampoco deben asomar. En ese caso salen del arroba.
     */
    public function initials(): string
    {
        $base = ltrim($this->publicName(), '@');

        $palabras = preg_split('/[\s_.\-]+/', $base, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letras = array_map(fn (string $p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($palabras, 0, 2));

        return implode('', $letras) ?: '?';
    }

    /**
     * Un tono estable para el placeholder, sacado del usuario.
     *
     * Con el mismo arroba sale siempre el mismo color, así una cara sin foto
     * igual se reconoce de una lista a otra. Es decorativo: el contraste del
     * texto no depende de esto.
     */
    public function avatarHue(): int
    {
        return crc32((string) $this->username) % 360;
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
     *
     * Un perfil privado no muestra ninguna a quien no lo sigue, ni siquiera
     * las marcadas como públicas: si las mostrara, «perfil privado» no querría
     * decir nada.
     */
    public function visibleWishlistsFor(User $viewer): HasMany
    {
        if ($viewer->id === $this->id) {
            return $this->wishlists();
        }

        if ($this->is_private && ! $this->isFollowedBy($viewer)) {
            return $this->wishlists()->whereRaw('1 = 0');
        }

        return $this->wishlists()->public();
    }

    // --- Seguidores -------------------------------------------------------

    /**
     * Las filas donde esta persona es la seguida: su gente.
     */
    public function followerLinks(): HasMany
    {
        return $this->hasMany(Follow::class, 'followed_id');
    }

    /**
     * Las filas donde esta persona es la que sigue.
     */
    public function followingLinks(): HasMany
    {
        return $this->hasMany(Follow::class, 'follower_id');
    }

    /**
     * Las personas que siguen a esta, ya aceptadas.
     *
     * Es de donde salen los candidatos a los que se le puede dar una lista
     * privada: el dueño reparte entre gente que ya reconoció.
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'followed_id', 'follower_id')
            ->withPivot('status', 'responded_at')
            ->withTimestamps()
            ->wherePivot('status', FollowStatus::ACCEPTED->label());
    }

    /**
     * A quiénes sigue esta persona, ya aceptadas.
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'follower_id', 'followed_id')
            ->withPivot('status', 'responded_at')
            ->withTimestamps()
            ->wherePivot('status', FollowStatus::ACCEPTED->label());
    }

    /**
     * ¿Esta persona es seguida por $otro, con el seguimiento ya aceptado?
     */
    public function isFollowedBy(User $otro): bool
    {
        return $this->followerLinks()
            ->accepted()
            ->where('follower_id', $otro->id)
            ->exists();
    }

    /**
     * La fila de seguimiento de esta persona hacia $otro, en cualquier estado.
     * Sirve para saber si el botón dice «Seguir», «Pendiente» o «Dejar de
     * seguir» sin adivinar.
     */
    public function followTo(User $otro): ?Follow
    {
        return $this->followingLinks()->where('followed_id', $otro->id)->first();
    }

    /**
     * Un perfil público acepta al instante; uno privado deja pendiente.
     */
    public function followsAreAutoAccepted(): bool
    {
        return ! $this->is_private;
    }

    /**
     * Cuántas cosas esperan mi respuesta: seguimientos y accesos a listas.
     *
     * Lo pinta la barra en todas las páginas. Sin este número, una solicitud
     * pendiente solo se descubre entrando a la pantalla a propósito, que es
     * justo lo que no pasa cuando uno no sabe que hay algo esperando.
     */
    public function pendingRequestsCount(): int
    {
        return $this->followerLinks()->pending()->count()
            + WishlistAccess::query()
                ->whereIn('wishlist_id', $this->wishlists()->select('id'))
                ->pending()
                ->count();
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
