<?php

namespace Database\Factories;

use App\Models\Auktion;
use App\Models\AuktionGebot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuktionGebot>
 */
class AuktionGebotFactory extends Factory
{
    protected $model = AuktionGebot::class;

    public function definition(): array
    {
        return [
            'auktion_id' => Auktion::factory(),
            'user_id' => User::factory(),
            'bieter_name' => $this->faker->name(),
            'betrag_cent' => 1500,
        ];
    }
}
