<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\BookRequest;
use App\Models\User;
use App\Enums\BookType;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BookRequest>
 */
class BookRequestFactory extends Factory
{
    protected $model = BookRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => $this->faker->numberBetween(1, 500),
            'book_title' => $this->faker->sentence(3),
            'condition' => $this->faker->randomElement(['neu', 'gebraucht']),
            'completed' => false,
        ];
    }
}
