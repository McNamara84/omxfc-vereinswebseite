<?php

namespace Database\Factories;

use App\Enums\AuktionsStatus;
use App\Models\Auktion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auktion>
 */
class AuktionFactory extends Factory
{
    protected $model = Auktion::class;

    public function definition(): array
    {
        return [
            'titel' => $this->faker->sentence(3),
            'beschreibung_markdown' => $this->faker->paragraph(),
            'startbetrag_cent' => 1000,
            'mindestschritt_cent' => 100,
            'status' => AuktionsStatus::Laufend,
            'verkauft_at' => null,
        ];
    }
}
