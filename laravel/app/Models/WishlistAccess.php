<?php

namespace App\Models;

use App\Enums\AccessRequestStatus;
use App\Enums\AccessSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistAccess extends Model
{
    use HasFactory;

    protected $table = 'wishlist_accesses';

    protected $fillable = [
        'wishlist_id',
        'user_id',
        'status',
        'source',
        'message',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    /**
     * Quien pidió el acceso.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusEnum(): AccessRequestStatus
    {
        return AccessRequestStatus::fromLabel($this->status);
    }

    public function sourceEnum(): AccessSource
    {
        return AccessSource::fromLabel($this->source);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', AccessRequestStatus::APPROVED->label());
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AccessRequestStatus::PENDING->label());
    }
}
