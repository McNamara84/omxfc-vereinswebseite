<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BaxxEarningRule extends Model
{
    protected $fillable = [
        'action_key',
        'label',
        'description',
        'points',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope to only active rules.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the points for a given action key.
     * Returns 0 if the rule doesn't exist or is inactive.
     * Results are cached for 1 hour.
     */
    public static function getPointsFor(string $actionKey): int
    {
        return Cache::remember(
            "baxx_earning_rule_{$actionKey}",
            3600,
            function () use ($actionKey) {
                $rule = static::where('action_key', $actionKey)
                    ->where('is_active', true)
                    ->first();

                return $rule?->points ?? 0;
            }
        );
    }

    /**
     * Clear the cache for a specific action key or all rules.
     */
    public static function clearCache(?string $actionKey = null): void
    {
        if ($actionKey) {
            Cache::forget("baxx_earning_rule_{$actionKey}");
        } else {
            $rules = static::all();
            foreach ($rules as $rule) {
                Cache::forget("baxx_earning_rule_{$rule->action_key}");
            }
        }
    }

    protected static function booted(): void
    {
        static::saved(function (BaxxEarningRule $rule) {
            static::clearCache($rule->action_key);
        });

        static::deleted(function (BaxxEarningRule $rule) {
            static::clearCache($rule->action_key);
        });
    }
}
