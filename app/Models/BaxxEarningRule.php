<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BaxxEarningRule extends Model
{
    use HasFactory;

    private const CACHE_KEY_SUFFIX = '.v2';

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
        $attributes = Cache::remember(
            self::cacheKey($actionKey),
            3600,
            fn () => static::query()
                ->where('action_key', $actionKey)
                ->where('is_active', true)
                ->first()?->getAttributes()
        );

        if (! is_array($attributes)) {
            return null;
        }

        $rule = new static;
        $rule->exists = true;
        $rule->setRawAttributes($attributes, true);

        return $rule;
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
            Cache::forget(self::cacheKey($actionKey));
            Cache::forget(self::legacyCacheKey($actionKey));
        } else {
            $rules = static::all();
            foreach ($rules as $rule) {
                Cache::forget(self::cacheKey($rule->action_key));
                Cache::forget(self::legacyCacheKey($rule->action_key));
            }
        }
    }

    private static function cacheKey(string $actionKey): string
    {
        return self::legacyCacheKey($actionKey).self::CACHE_KEY_SUFFIX;
    }

    private static function legacyCacheKey(string $actionKey): string
    {
        return "baxx_earning_rule_model_{$actionKey}";
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
