<?php

namespace Database\Factories;

use App\Enums\KassenbuchEditReasonType;
use App\Models\KassenbuchEditRequest;
use App\Models\KassenbuchEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KassenbuchEditRequest>
 */
class KassenbuchEditRequestFactory extends Factory
{
    protected $model = KassenbuchEditRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kassenbuch_entry_id' => KassenbuchEntry::factory(),
            'requested_by' => User::factory(),
            'processed_by' => null,
            'reason_type' => fake()->randomElement(KassenbuchEditReasonType::values()),
            'reason_text' => fake()->optional(0.5)->sentence(),
            'status' => KassenbuchEditRequest::STATUS_PENDING,
            'rejection_reason' => null,
            'processed_at' => null,
        ];
    }

    /**
     * Indicate that the request is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KassenbuchEditRequest::STATUS_PENDING,
            'processed_by' => null,
            'processed_at' => null,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Indicate that the request is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KassenbuchEditRequest::STATUS_APPROVED,
            'processed_by' => User::factory(),
            'processed_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    /**
     * Indicate that the request is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KassenbuchEditRequest::STATUS_REJECTED,
            'processed_by' => User::factory(),
            'processed_at' => now(),
            'rejection_reason' => fake()->optional(0.7)->sentence(),
        ]);
    }

    /**
     * Set a specific reason type.
     */
    public function withReasonType(KassenbuchEditReasonType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'reason_type' => $type->value,
        ]);
    }

    /**
     * Set reason type to "Sonstiges" with required text.
     */
    public function sonstiges(?string $reasonText = null): static
    {
        return $this->state(fn (array $attributes) => [
            'reason_type' => KassenbuchEditReasonType::Sonstiges->value,
            'reason_text' => $reasonText ?? fake()->sentence(),
        ]);
    }
}
