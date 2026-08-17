<?php

namespace App\Models;

use App\Enums\FollowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Follow extends Model
{
    use HasFactory;

    protected $fillable = [
        'follower_id',
        'followed_id',
        'status',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    /**
     * Quien sigue.
     */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    /**
     * A quien sigue.
     */
    public function followed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followed_id');
    }

    public function statusEnum(): FollowStatus
    {
        return FollowStatus::fromLabel($this->status);
    }

    public function isAccepted(): bool
    {
        return $this->statusEnum()->isActive();
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', FollowStatus::ACCEPTED->label());
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', FollowStatus::PENDING->label());
    }
}
