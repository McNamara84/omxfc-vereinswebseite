<?php

namespace App\Models;

use Database\Factories\MaddraxikonRewardPolicyTierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MaddraxikonRewardPolicyTier extends Model
{
    /** @use HasFactory<MaddraxikonRewardPolicyTierFactory> */
    use HasFactory;

    protected $fillable = [
        'maddraxikon_reward_policy_id',
        'minimum_added_bytes',
        'points',
    ];

    protected function casts(): array
    {
        return [
            'minimum_added_bytes' => 'integer',
            'points' => 'integer',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(MaddraxikonRewardPolicy::class, 'maddraxikon_reward_policy_id');
    }

    protected static function booted(): void
    {
        $rejectPublishedPolicyMutation = static function (self $tier): void {
            $policy = $tier->relationLoaded('policy')
                ? $tier->policy
                : MaddraxikonRewardPolicy::query()->find($tier->maddraxikon_reward_policy_id);

            if ($policy?->isPublished()) {
                throw new LogicException('Stufen einer veröffentlichten Maddraxikon-Regel sind unveränderlich.');
            }
        };

        static::creating($rejectPublishedPolicyMutation);
        static::updating($rejectPublishedPolicyMutation);
        static::deleting($rejectPublishedPolicyMutation);
    }
}
