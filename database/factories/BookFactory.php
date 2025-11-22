<?php

namespace Database\Factories;

use App\Enums\BookType;
use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'roman_number' => $this->faker->unique()->numberBetween(1, 9999),
            'title' => $this->faker->sentence(3),
            'author' => $this->faker->name(),
            'type' => $this->faker->randomElement(BookType::cases()),
        ];
    }
}
