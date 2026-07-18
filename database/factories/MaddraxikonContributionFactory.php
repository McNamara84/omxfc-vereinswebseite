<?php

namespace Database\Factories;

use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonContributionType;
use App\Models\MaddraxikonContribution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaddraxikonContribution>
 */
class MaddraxikonContributionFactory extends Factory
{
    protected $model = MaddraxikonContribution::class;

    public function definition(): array
    {
        $occurredAt = now()->subHours(25);

        return [
            'wiki_key' => config('maddraxikon.wiki_key', 'maddraxikon-de'),
            'rc_id' => fake()->unique()->numberBetween(1, 2_000_000_000),
            'revision_id' => fake()->unique()->numberBetween(1, 2_000_000_000),
            'parent_revision_id' => fake()->numberBetween(1, 2_000_000_000),
            'page_id' => fake()->numberBetween(1, 2_000_000_000),
            'namespace_id' => 0,
            'page_title' => fake()->sentence(3),
            'wiki_user_id' => fake()->numberBetween(1, 2_000_000_000),
            'wiki_username' => fake()->userName(),
            'account_link_id' => null,
            'user_id' => User::factory(),
            'type' => MaddraxikonContributionType::Edit,
            'minor' => false,
            'bot' => false,
            'anonymous' => false,
            'redirect' => false,
            'user_hidden' => false,
            'old_size' => 600,
            'new_size' => 650,
            'tags' => [],
            'occurred_at' => $occurredAt,
            'session_anchor_revision_id' => null,
            'status' => MaddraxikonContributionStatus::Pending,
            'status_reason' => null,
            'eligible_after' => $occurredAt->copy()->addDay(),
            'checked_at' => null,
            'evaluation_attempts' => 0,
            'last_evaluation_error' => null,
            'last_evaluation_error_at' => null,
        ];
    }

    public function newArticle(): static
    {
        return $this->state(fn (): array => [
            'type' => MaddraxikonContributionType::New,
            'parent_revision_id' => 0,
        ]);
    }
}
