<?php

namespace Database\Factories;

use App\Models\Download;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Download>
 */
class DownloadFactory extends Factory
{
    protected $model = Download::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->unique()->sentence(3),
            'description' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(['Klemmbaustein-Anleitungen', 'Fanstories', 'Sonstiges']),
            'file_path' => 'downloads/test-'.$this->faker->unique()->slug(2).'.pdf',
            'original_filename' => $this->faker->slug(2).'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(10000, 5000000),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the download is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
