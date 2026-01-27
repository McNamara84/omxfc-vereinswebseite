<?php

namespace Database\Factories;

use App\Enums\KassenbuchEntryType;
use App\Models\KassenbuchEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KassenbuchEntry>
 */
class KassenbuchEntryFactory extends Factory
{
    protected $model = KassenbuchEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $typ = fake()->randomElement(KassenbuchEntryType::cases());
        $betrag = fake()->randomFloat(2, 1, 500);

        return [
            'team_id' => Team::factory(),
            'created_by' => User::factory(),
            'buchungsdatum' => fake()->dateTimeBetween('-1 year', 'now'),
            'betrag' => $typ === KassenbuchEntryType::Ausgabe ? -$betrag : $betrag,
            'beschreibung' => fake()->sentence(3),
            'typ' => $typ->value,
        ];
    }

    /**
     * Indicate that the entry is an income (Einnahme).
     */
    public function einnahme(?float $betrag = null): static
    {
        return $this->state(fn (array $attributes) => [
            'typ' => KassenbuchEntryType::Einnahme->value,
            'betrag' => abs($betrag ?? fake()->randomFloat(2, 1, 500)),
        ]);
    }

    /**
     * Indicate that the entry is an expense (Ausgabe).
     */
    public function ausgabe(?float $betrag = null): static
    {
        return $this->state(fn (array $attributes) => [
            'typ' => KassenbuchEntryType::Ausgabe->value,
            'betrag' => -abs($betrag ?? fake()->randomFloat(2, 1, 500)),
        ]);
    }

    /**
     * Mark the entry as edited.
     */
    public function edited(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_edited_by' => User::factory(),
            'last_edited_at' => now(),
            'last_edit_reason' => 'Tippfehler: Korrektur der Beschreibung',
        ]);
    }
}
