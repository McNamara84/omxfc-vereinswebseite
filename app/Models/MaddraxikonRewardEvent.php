<?php

namespace App\Models;

use App\Enums\MaddraxikonRewardEventStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MaddraxikonRewardEventFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaddraxikonRewardEvent extends Model
{
    public const ACTION_EDIT_SESSION = 'maddraxikon_edit_session';

    public const ACTION_NEW_ARTICLE = 'maddraxikon_new_article';

    /** @use HasFactory<MaddraxikonRewardEventFactory> */
    use HasFactory;

    protected $fillable = [
        'wiki_key',
        'user_id',
        'account_link_id',
        'source_contribution_id',
        'action_key',
        'source_key',
        'source_revision_id',
        'session_anchor_revision_id',
        'activity_date',
        'sequence_number',
        'baxx_earning_rule_id',
        'maddraxikon_reward_policy_id',
        'maddraxikon_reward_policy_tier_id',
        'rule_points',
        'rule_every_count',
        'rule_updated_at',
        'policy_effective_from',
        'policy_effective_from_epoch',
        'measured_added_bytes',
        'matched_minimum_added_bytes',
        'policy_new_article_minimum_bytes',
        'calculation_mode',
        'candidate_points',
        'awarded_points',
        'capped_points',
        'status',
        'status_reason',
        'user_point_id',
        'reversal_user_point_id',
        'awarded_at',
        'activity_pending',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'source_revision_id' => 'integer',
            'session_anchor_revision_id' => 'integer',
            'activity_date' => 'date',
            'sequence_number' => 'integer',
            'rule_points' => 'integer',
            'rule_every_count' => 'integer',
            'rule_updated_at' => 'datetime',
            'policy_effective_from_epoch' => 'integer',
            'measured_added_bytes' => 'integer',
            'matched_minimum_added_bytes' => 'integer',
            'policy_new_article_minimum_bytes' => 'integer',
            'candidate_points' => 'integer',
            'awarded_points' => 'integer',
            'capped_points' => 'integer',
            'status' => MaddraxikonRewardEventStatus::class,
            'awarded_at' => 'datetime',
            'activity_pending' => 'boolean',
            'reversed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountLink(): BelongsTo
    {
        return $this->belongsTo(MaddraxikonAccountLink::class, 'account_link_id');
    }

    public function sourceContribution(): BelongsTo
    {
        return $this->belongsTo(MaddraxikonContribution::class, 'source_contribution_id');
    }

    public function earningRule(): BelongsTo
    {
        return $this->belongsTo(BaxxEarningRule::class, 'baxx_earning_rule_id');
    }

    public function rewardPolicy(): BelongsTo
    {
        return $this->belongsTo(
            MaddraxikonRewardPolicy::class,
            'maddraxikon_reward_policy_id'
        );
    }

    public function rewardPolicyTier(): BelongsTo
    {
        return $this->belongsTo(
            MaddraxikonRewardPolicyTier::class,
            'maddraxikon_reward_policy_tier_id'
        );
    }

    public function userPoint(): BelongsTo
    {
        return $this->belongsTo(UserPoint::class, 'user_point_id');
    }

    public function reversalUserPoint(): BelongsTo
    {
        return $this->belongsTo(UserPoint::class, 'reversal_user_point_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    protected function policyEffectiveFrom(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): ?CarbonImmutable {
                if (isset($attributes['policy_effective_from_epoch'])) {
                    return CarbonImmutable::createFromTimestampUTC(
                        (int) $attributes['policy_effective_from_epoch']
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
                        'policy_effective_from' => null,
                        'policy_effective_from_epoch' => null,
                    ];
                }

                $instant = $value instanceof DateTimeInterface
                    ? CarbonImmutable::instance($value)
                    : CarbonImmutable::parse(
                        $value,
                        (string) config('app.timezone', 'UTC')
                    );

                return [
                    'policy_effective_from' => $instant->utc()->format('Y-m-d H:i:s'),
                    'policy_effective_from_epoch' => $instant->getTimestamp(),
                ];
            },
        );
    }
}
