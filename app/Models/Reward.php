<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'category',
        'slug',
        'cost_baxx',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'cost_baxx' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Reward $reward) {
            if (empty($reward->slug)) {
                $baseSlug = Str::slug($reward->title);
                $slug = $baseSlug;
                $counter = 2;

                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$baseSlug}-{$counter}";
                    $counter++;
                }

                $reward->slug = $slug;
            }
        });
    }

    /**
     * All purchases for this reward.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(RewardPurchase::class);
    }

    /**
     * Only active (non-refunded) purchases.
     */
    public function activePurchases(): HasMany
    {
        return $this->hasMany(RewardPurchase::class)->whereNull('refunded_at');
    }

    /**
     * Scope to only active rewards.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
