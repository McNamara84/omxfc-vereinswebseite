<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BaxxEarningRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'action_key',
        'label',
        'description',
        'points',
        'every_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'every_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function getActiveRuleFor(string $actionKey): ?self
    {
        return Cache::remember(
            "baxx_earning_rule_model_{$actionKey}",
            3600,
            fn () => static::query()
                ->where('action_key', $actionKey)
                ->where('is_active', true)
                ->first()
        );
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
        return static::getActiveRuleFor($actionKey)?->points ?? 0;
    }

    public static function getEveryCountFor(string $actionKey): int
    {
        return static::getActiveRuleFor($actionKey)?->every_count ?? 1;
    }

    /**
     * Clear the cache for a specific action key or all rules.
     */
    public static function clearCache(?string $actionKey = null): void
    {
        if ($actionKey) {
            Cache::forget("baxx_earning_rule_model_{$actionKey}");
        } else {
            $rules = static::all();
            foreach ($rules as $rule) {
                Cache::forget("baxx_earning_rule_model_{$rule->action_key}");
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
