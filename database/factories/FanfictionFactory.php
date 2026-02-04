<?php

namespace Database\Factories;

use App\Enums\FanfictionStatus;
use App\Models\Fanfiction;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Fanfiction>
 */
class FanfictionFactory extends Factory
{
    protected $model = Fanfiction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $useExternalAuthor = $this->faker->boolean(30);

        return [
            'team_id' => Team::factory(),
            'user_id' => $useExternalAuthor ? null : User::factory(),
            'created_by' => User::factory(),
            'title' => $this->faker->sentence(4),
            'author_name' => $this->faker->name(),
            'content' => $this->generateStoryContent(),
            'photos' => null,
            'status' => FanfictionStatus::Draft,
            'published_at' => null,
        ];
    }

    /**
     * Indicate that the fanfiction is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FanfictionStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the fanfiction is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FanfictionStatus::Draft,
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the fanfiction has photos.
     */
    public function withPhotos(int $count = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'photos' => array_map(
                fn ($i) => "fanfiction/photo-{$i}.jpg",
                range(1, min($count, 5))
            ),
        ]);
    }

    /**
     * Indicate that the fanfiction has an external author.
     */
    public function externalAuthor(?string $name = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'author_name' => $name ?? $this->faker->name(),
        ]);
    }

    /**
     * Generate realistic story content with Markdown formatting.
     */
    private function generateStoryContent(): string
    {
        $paragraphs = $this->faker->paragraphs(rand(3, 6));
        $content = '';

        foreach ($paragraphs as $index => $paragraph) {
            if ($index === 0) {
                $content .= '**'.$this->faker->sentence()."**\n\n";
            }

            $content .= $paragraph."\n\n";

            if ($index === 1 && rand(0, 1)) {
                $content .= '> '.$this->faker->sentence()."\n\n";
            }
        }

        return trim($content);
    }
}
