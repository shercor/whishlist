<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'products_count',
    ];

    protected function casts(): array
    {
        return [
            'products_count' => 'integer',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * Los tags más usados primero, para sugerencias y nubes de tags.
     */
    public function scopePopular(Builder $query): Builder
    {
        return $query->orderByDesc('products_count');
    }
}
