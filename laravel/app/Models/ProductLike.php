<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El voto de un usuario a una ficha del catálogo.
 *
 * Es una tabla pivote con modelo propio a propósito: los timestamps sirven
 * para poder distinguir después una ficha que gustó siempre de una que se
 * llenó de votos en un día.
 */
class ProductLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
