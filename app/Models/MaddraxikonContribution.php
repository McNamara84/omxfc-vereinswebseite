<?php

namespace App\Models;

use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonContributionType;
use Carbon\CarbonImmutable;
use Database\Factories\MaddraxikonContributionFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaddraxikonContribution extends Model
{
    /** @use HasFactory<MaddraxikonContributionFactory> */
    use HasFactory;

    protected $fillable = [
        'wiki_key',
        'rc_id',
        'revision_id',
        'parent_revision_id',
        'page_id',
        'namespace_id',
        'page_title',
        'wiki_user_id',
        'wiki_username',
        'account_link_id',
        'user_id',
        'type',
        'minor',
        'bot',
        'anonymous',
        'redirect',
        'user_hidden',
        'old_size',
        'new_size',
        'tags',
        'occurred_at',
        'occurred_at_epoch',
        'session_anchor_revision_id',
        'status',
        'status_reason',
        'eligible_after',
        'eligible_after_epoch',
        'checked_at',
        'evaluation_attempts',
        'last_evaluation_error',
        'last_evaluation_error_at',
    ];

    protected function casts(): array
    {
        return [
            'rc_id' => 'integer',
            'revision_id' => 'integer',
            'parent_revision_id' => 'integer',
            'page_id' => 'integer',
            'namespace_id' => 'integer',
            'wiki_user_id' => 'integer',
            'type' => MaddraxikonContributionType::class,
            'minor' => 'boolean',
            'bot' => 'boolean',
            'anonymous' => 'boolean',
            'redirect' => 'boolean',
            'user_hidden' => 'boolean',
            'old_size' => 'integer',
            'new_size' => 'integer',
            'tags' => 'array',
            'occurred_at_epoch' => 'integer',
            'session_anchor_revision_id' => 'integer',
            'status' => MaddraxikonContributionStatus::class,
            'eligible_after_epoch' => 'integer',
            'checked_at' => 'datetime',
            'evaluation_attempts' => 'integer',
            'last_evaluation_error_at' => 'datetime',
        ];
    }

    protected function occurredAt(): Attribute
    {
        return $this->utcInstantAttribute('occurred_at');
    }

    protected function eligibleAfter(): Attribute
    {
        return $this->utcInstantAttribute('eligible_after');
    }

    public function accountLink(): BelongsTo
    {
        return $this->belongsTo(MaddraxikonAccountLink::class, 'account_link_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rewardEvents(): HasMany
    {
        return $this->hasMany(MaddraxikonRewardEvent::class, 'source_contribution_id');
    }

    public function occurredAtUtc(): CarbonImmutable
    {
        if ($this->occurred_at_epoch !== null) {
            return CarbonImmutable::createFromTimestampUTC(
                $this->occurred_at_epoch
            );
        }

        return CarbonImmutable::instance($this->occurred_at)->utc();
    }

    public function eligibleAfterUtc(): CarbonImmutable
    {
        if ($this->eligible_after_epoch !== null) {
            return CarbonImmutable::createFromTimestampUTC(
                $this->eligible_after_epoch
            );
        }

        return CarbonImmutable::instance($this->eligible_after)->utc();
    }

    private function utcInstantAttribute(string $column): Attribute
    {
        $epochColumn = $column.'_epoch';

        return Attribute::make(
            get: function (
                mixed $value,
                array $attributes
            ) use ($epochColumn): ?CarbonImmutable {
                if (isset($attributes[$epochColumn])) {
                    return CarbonImmutable::createFromTimestampUTC(
                        (int) $attributes[$epochColumn]
                    );
                }

                if ($value === null) {
                    return null;
                }

                return CarbonImmutable::parse(
                    $value,
                    (string) config('app.timezone', 'UTC')
                );
            },
            set: function (mixed $value) use (
                $column,
                $epochColumn
            ): array {
                if ($value === null) {
                    return [
                        $column => null,
                        $epochColumn => null,
                    ];
                }

                $instant = $value instanceof DateTimeInterface
                    ? CarbonImmutable::instance($value)
                    : CarbonImmutable::parse(
                        $value,
                        (string) config('app.timezone', 'UTC')
                    );

                return [
                    $column => $instant->utc()->format('Y-m-d H:i:s'),
                    $epochColumn => $instant->getTimestamp(),
                ];
            }
        );
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->where('status', MaddraxikonContributionStatus::Pending->value)
            ->where(function (Builder $query): void {
                $query
                    ->where(
                        'eligible_after_epoch',
                        '<=',
                        now()->getTimestamp()
                    )
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->whereNull('eligible_after_epoch')
                            ->where('eligible_after', '<=', now());
                    });
            });
    }
}
