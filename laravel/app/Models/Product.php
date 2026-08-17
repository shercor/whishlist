<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'created_by_user_id',
        'name',
        'description',
        'url',
        'image_url',
        'image_path',
        'reference_price',
        'currency',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'reference_price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(ProductLike::class);
    }

    /**
     * Si a este usuario ya le gustaba, para pintar el botón marcado.
     */
    public function isLikedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Si el contador ya viene cargado con withCount, se usa y no se
        // consulta de nuevo: esta pregunta se hace una vez por resultado de
        // búsqueda y son hasta 24 por pantalla.
        if (isset($this->attributes['mine_likes_count'])) {
            return $this->attributes['mine_likes_count'] > 0;
        }

        return $this->likes()->where('user_id', $user->id)->exists();
    }

    /**
     * De dónde sale la foto: primero la que alguien subió, y si no hay, la del
     * sitio de la tienda. Devuelve null si el producto no tiene ninguna.
     */
    public function imageSrc(): ?string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        return $this->image_url;
    }

    /**
     * Solo el catálogo curado, que es lo único buscable por todos.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * El catálogo más los productos privados que creó el propio usuario.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user) {
            $query->where('is_public', true)
                ->orWhere('created_by_user_id', $user->id);
        });
    }

    /**
     * Búsqueda por texto libre sobre el índice fulltext de name y description.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->whereFullText(['name', 'description'], $term);
    }

    /**
     * Búsqueda para el buscador del catálogo, donde la gente escribe a medias:
     * "pelu" tiene que encontrar "Peluche". Modo booleano con comodín al final
     * de cada palabra, que sigue usando el mismo índice fulltext.
     *
     * Ojo: InnoDB ignora los términos de menos de tres letras.
     */
    public function scopeSearchPrefix(Builder $query, string $term): Builder
    {
        $palabras = collect(preg_split('/\s+/', trim($term)))
            ->filter(fn (string $palabra) => mb_strlen($palabra) >= 3)
            // El modo booleano trata a estos caracteres como operadores.
            ->map(fn (string $palabra) => preg_replace('/[+\-><()~*"@]/', '', $palabra).'*')
            ->filter(fn (string $palabra) => $palabra !== '*');

        if ($palabras->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereFullText(['name', 'description'], $palabras->implode(' '), ['mode' => 'boolean']);
    }

    /**
     * El orden con que se le muestra el catálogo a la gente.
     *
     * El catálogo tiene fichas repetidas del mismo producto —las va creando
     * quien no encuentra la suya— y no todas están igual de cuidadas. Manda el
     * voto de los usuarios; entre las que empatan en cero, que es el estado
     * inicial de todo, gana la que al menos tiene foto, porque una ficha sin
     * imagen no le sirve a nadie para decidir.
     */
    public function scopeBestFirst(Builder $query): Builder
    {
        return $query->withCount('likes')
            ->orderByDesc('likes_count')
            ->orderByRaw('(image_path IS NOT NULL OR image_url IS NOT NULL) DESC')
            ->orderBy('name');
    }

    /**
     * Marca, de una sola consulta, cuáles de los productos traídos ya tienen
     * el «me gusta» de este usuario.
     */
    public function scopeWithMyLike(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query;
        }

        return $query->withCount([
            'likes as mine_likes_count' => fn (Builder $query) => $query->where('user_id', $user->id),
        ]);
    }
}
