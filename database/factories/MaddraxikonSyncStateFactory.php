<?php

namespace Database\Factories;

use App\Models\MaddraxikonSyncState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaddraxikonSyncState>
 */
class MaddraxikonSyncStateFactory extends Factory
{
    protected $model = MaddraxikonSyncState::class;

    public function definition(): array
    {
        return [
            'wiki_key' => config('maddraxikon.wiki_key', 'maddraxikon-de'),
            'watermark_at' => now()->subMinutes(15),
            'initial_watermark_at' => now()->subDay(),
            'last_started_at' => now()->subMinute(),
            'last_succeeded_at' => now(),
            'last_error_at' => null,
            'last_error' => null,
            'consecutive_failures' => 0,
            'last_imported_count' => 0,
            'last_seen_rc_id' => null,
            'recovery_required_at' => null,
            'recovery_from_at' => null,
            'recovery_until_at' => null,
            'last_recovery_succeeded_at' => null,
            'last_recovered_from_at' => null,
            'last_recovered_until_at' => null,
            'last_recovered_count' => 0,
        ];
    }
}
