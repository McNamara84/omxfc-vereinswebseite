<?php

namespace Database\Factories;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Models\MaddraxikonAccountLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaddraxikonAccountLink>
 */
class MaddraxikonAccountLinkFactory extends Factory
{
    protected $model = MaddraxikonAccountLink::class;

    public function definition(): array
    {
        $verifiedAt = now()->subDay();

        return [
            'user_id' => User::factory(),
            'wiki_key' => config('maddraxikon.wiki_key', 'maddraxikon-de'),
            'oauth_subject' => fake()->unique()->uuid(),
            'wiki_user_id' => fake()->unique()->numberBetween(1, 2_000_000_000),
            'wiki_username' => fake()->unique()->userName(),
            'status' => MaddraxikonAccountLinkStatus::Active,
            'verification_method' => 'oauth2',
            'first_verified_at' => $verifiedAt,
            'verified_at' => $verifiedAt,
            'disconnected_at' => null,
            'consent_version' => config('maddraxikon.consent_version', '2026-07-18'),
            'consented_at' => $verifiedAt,
        ];
    }

    public function disconnected(): static
    {
        return $this->state(fn (): array => [
            'status' => MaddraxikonAccountLinkStatus::Disconnected,
            'disconnected_at' => now(),
        ]);
    }
}
