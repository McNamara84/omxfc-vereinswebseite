<?php

namespace Database\Factories;

use App\Enums\BookType;
use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
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
            'maddraxikon_page_id' => null,
            'maddraxikon_page_title' => null,
            'maddraxikon_page_verified_at' => null,
        ];
    }

    public function mapped(?int $pageId = null, ?string $pageTitle = null): static
    {
        return $this->state(fn (): array => [
            'maddraxikon_page_id' => $pageId ?? fake()->unique()->numberBetween(1, 2_000_000_000),
            'maddraxikon_page_title' => $pageTitle ?? fake()->unique()->words(3, true),
            'maddraxikon_page_verified_at' => now(),
        ]);
    }
}
