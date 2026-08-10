<?php

namespace Database\Factories;

use App\Models\MaddraxikonRatingSyncState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaddraxikonRatingSyncState>
 */
class MaddraxikonRatingSyncStateFactory extends Factory
{
    protected $model = MaddraxikonRatingSyncState::class;

    public function definition(): array
    {
        return [
            'wiki_key' => config('maddraxikon.wiki_key', 'maddraxikon-de'),
            'last_started_at' => now(),
            'last_succeeded_at' => now(),
            'last_error_at' => null,
            'last_error_category' => null,
            'consecutive_failures' => 0,
            'last_candidate_count' => 0,
            'last_updated_count' => 0,
            'last_removed_count' => 0,
            'last_skipped_count' => 0,
        ];
    }
}
