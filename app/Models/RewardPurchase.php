<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reward_id',
        'cost_baxx',
        'purchased_at',
        'refunded_at',
        'refunded_by',
    ];

    protected function casts(): array
    {
        return [
            'cost_baxx' => 'integer',
            'purchased_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * The user who made the purchase.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The reward that was purchased.
     */
    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    /**
     * The admin who refunded this purchase (if applicable).
     */
    public function refundedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    /**
     * Scope to only active (non-refunded) purchases.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('refunded_at');
    }

    /**
     * Scope to only refunded purchases.
     */
    public function scopeRefunded(Builder $query): Builder
    {
        return $query->whereNotNull('refunded_at');
    }

    /**
     * Check if this purchase has been refunded.
     */
    public function isRefunded(): bool
    {
        return $this->refunded_at !== null;
    }
}
