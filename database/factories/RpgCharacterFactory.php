<?php

namespace Database\Factories;

use App\Models\RpgCharacter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RpgCharacter>
 */
class RpgCharacterFactory extends Factory
{
    protected $model = RpgCharacter::class;

    public function definition(): array
    {
        $name = $this->faker->firstName().' '.$this->faker->lastName();

        return [
            'user_id' => User::factory(),
            'character_name' => $name,
            'payload' => [
                'character' => [
                    'player_name' => $this->faker->firstName(),
                    'character_name' => $name,
                    'gender' => 'maennlich',
                    'race' => 'Barbar',
                    'culture' => 'Landbewohner',
                    'description' => '',
                    'equipment' => '',
                ],
                'attributes' => ['st' => '2', 'ge' => '1'],
                'skills' => [],
                'advantages' => ['Zaeh'],
                'disadvantages' => [],
                'advantage_details' => [],
                'disadvantage_details' => [],
                'advantage_counts' => [],
                'equipment' => [
                    'clothing' => null,
                    'items' => [],
                    'ammunition' => [],
                    'notes' => '',
                ],
            ],
        ];
    }
}
