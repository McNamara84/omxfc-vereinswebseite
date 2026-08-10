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
            $policyIds = collect([
                $tier->getRawOriginal('maddraxikon_reward_policy_id'),
                $tier->maddraxikon_reward_policy_id,
            ])
                ->filter(fn (mixed $policyId): bool => $policyId !== null)
                ->map(fn (mixed $policyId): int => (int) $policyId)
                ->unique()
                ->values();

            if (
                MaddraxikonRewardPolicy::query()
                    ->whereKey($policyIds->all())
                    ->published()
                    ->exists()
            ) {
                throw new LogicException('Stufen einer veröffentlichten Maddraxikon-Regel sind unveränderlich.');
            }
        };

        static::creating($rejectPublishedPolicyMutation);
        static::updating($rejectPublishedPolicyMutation);
        static::deleting($rejectPublishedPolicyMutation);
    }
}
