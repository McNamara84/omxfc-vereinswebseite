<?php

namespace Database\Factories;

use App\Enums\MaddraxikonRewardEventStatus;
use App\Models\MaddraxikonRewardEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaddraxikonRewardEvent>
 */
class MaddraxikonRewardEventFactory extends Factory
{
    protected $model = MaddraxikonRewardEvent::class;

    public function definition(): array
    {
        $sourceRevisionId = fake()->unique()->numberBetween(1, 2_000_000_000);

        return [
            'wiki_key' => config('maddraxikon.wiki_key', 'maddraxikon-de'),
            'source_contribution_id' => null,
            'user_id' => User::factory(),
            'account_link_id' => null,
            'action_key' => MaddraxikonRewardEvent::ACTION_EDIT_SESSION,
            'source_key' => 'edit-session:'.fake()->unique()->numberBetween(1, 2_000_000_000),
            'source_revision_id' => $sourceRevisionId,
            'session_anchor_revision_id' => $sourceRevisionId,
            'activity_date' => now()->toDateString(),
            'sequence_number' => 5,
            'baxx_earning_rule_id' => null,
            'rule_points' => 1,
            'rule_every_count' => 5,
            'rule_updated_at' => now(),
            'candidate_points' => 1,
            'awarded_points' => 0,
            'capped_points' => 0,
            'status' => MaddraxikonRewardEventStatus::EvaluatedNoAward,
            'status_reason' => null,
            'user_point_id' => null,
            'reversal_user_point_id' => null,
            'awarded_at' => null,
            'activity_pending' => false,
            'reversed_at' => null,
            'reversed_by' => null,
            'reversal_reason' => null,
        ];
    }
}
