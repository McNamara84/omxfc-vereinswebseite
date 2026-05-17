<?php

namespace Database\Factories;

use App\Enums\NewsletterAusgabeStatus;
use App\Models\NewsletterAusgabe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterAusgabe>
 */
class NewsletterAusgabeFactory extends Factory
{
    protected $model = NewsletterAusgabe::class;

    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(3),
            'topics' => [
                [
                    'title' => fake()->sentence(2),
                    'content' => fake()->paragraph(),
                ],
            ],
            'recipient_roles' => ['Mitglied'],
            'status' => NewsletterAusgabeStatus::Entwurf,
            'sent_at' => now(),
            'published_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => NewsletterAusgabeStatus::Veroeffentlicht,
            'published_at' => now(),
        ]);
    }
}