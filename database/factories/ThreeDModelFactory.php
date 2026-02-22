<?php

namespace Database\Factories;

use App\Models\ThreeDModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ThreeDModel>
 */
class ThreeDModelFactory extends Factory
{
    protected $model = ThreeDModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $format = $this->faker->randomElement(['stl', 'obj', 'fbx']);

        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'file_path' => '3d-models/'.$this->faker->uuid().'.'.$format,
            'file_format' => $format,
            'file_size' => $this->faker->numberBetween(1024, 104857600),
            'thumbnail_path' => null,
            'required_baxx' => $this->faker->numberBetween(1, 50),
            'uploaded_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the model has a thumbnail.
     */
    public function withThumbnail(): static
    {
        return $this->state(fn () => [
            'thumbnail_path' => '3d-thumbnails/'.$this->faker->uuid().'.jpg',
        ]);
    }
}
