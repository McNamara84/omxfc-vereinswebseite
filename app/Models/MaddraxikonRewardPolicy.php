<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MaddraxikonRewardPolicyFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class MaddraxikonRewardPolicy extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /** @use HasFactory<MaddraxikonRewardPolicyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'effective_from',
        'effective_from_epoch',
        'edit_sessions_enabled',
        'new_articles_enabled',
        'new_article_minimum_bytes',
        'new_article_points',
        'created_by',
        'published_by',
        'published_at',
        'published_at_epoch',
    ];

    protected function casts(): array
    {
        return [
            'effective_from_epoch' => 'integer',
            'edit_sessions_enabled' => 'boolean',
            'new_articles_enabled' => 'boolean',
            'new_article_minimum_bytes' => 'integer',
            'new_article_points' => 'integer',
            'published_at_epoch' => 'integer',
        ];
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(MaddraxikonRewardPolicyTier::class)
            ->orderBy('minimum_added_bytes');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function rewardEvents(): HasMany
    {
        return $this->hasMany(MaddraxikonRewardEvent::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeEffectiveAt(Builder $query, CarbonImmutable $instant): Builder
    {
        return $query->published()
            ->where('effective_from_epoch', '<=', $instant->getTimestamp());
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    protected function effectiveFrom(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): ?CarbonImmutable {
                if (isset($attributes['effective_from_epoch'])) {
                    return CarbonImmutable::createFromTimestampUTC(
                        (int) $attributes['effective_from_epoch']
                    );
                }

                return $value === null
                    ? null
                    : CarbonImmutable::parse(
                        $value,
                        (string) config('app.timezone', 'UTC')
                    )->utc();
            },
            set: function (mixed $value): array {
                if ($value === null) {
                    return [
                        'effective_from' => null,
                        'effective_from_epoch' => null,
                    ];
                }

                $instant = $value instanceof DateTimeInterface
                    ? CarbonImmutable::instance($value)
                    : CarbonImmutable::parse(
                        $value,
                        (string) config('app.timezone', 'UTC')
                    );

                return [
                    'effective_from' => $instant->utc()->format('Y-m-d H:i:s'),
                    'effective_from_epoch' => $instant->getTimestamp(),
                ];
            },
        );
    }

    protected function publishedAt(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): ?CarbonImmutable {
                if (isset($attributes['published_at_epoch'])) {
                    return CarbonImmutable::createFromTimestampUTC(
                        (int) $attributes['published_at_epoch']
                    );
                }

                return $value === null
                    ? null
                    : CarbonImmutable::parse(
                        $value,
                        (string) config('app.timezone', 'UTC')
                    )->utc();
            },
            set: function (mixed $value): array {
                if ($value === null) {
                    return [
                        'published_at' => null,
                        'published_at_epoch' => null,
                    ];
                }

                $instant = $value instanceof DateTimeInterface
                    ? CarbonImmutable::instance($value)
                    : CarbonImmutable::parse(
                        $value,
                        (string) config('app.timezone', 'UTC')
                    );

                return [
                    'published_at' => $instant->utc()->format('Y-m-d H:i:s'),
                    'published_at_epoch' => $instant->getTimestamp(),
                ];
            },
        );
    }

    protected static function booted(): void
    {
        static::updating(function (self $policy): void {
            if ($policy->getOriginal('status') === self::STATUS_PUBLISHED) {
                throw new LogicException('Veröffentlichte Maddraxikon-Regeln sind unveränderlich.');
            }
        });

        static::deleting(function (self $policy): void {
            if ($policy->isPublished()) {
                throw new LogicException('Veröffentlichte Maddraxikon-Regeln können nicht gelöscht werden.');
            }
        });
    }
}
