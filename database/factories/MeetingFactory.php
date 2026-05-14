<?php

namespace Database\Factories;

use App\Enums\MeetingRhythmType;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = ucfirst($this->faker->unique()->words(2, true));

        return [
            'title' => $title,
            'slug' => $this->faker->unique()->slug(2),
            'zoom_url' => 'https://example.com/'.$this->faker->unique()->slug(2),
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 999),
            'time_from' => '20:00',
            'time_to' => '21:00',
            'rhythm_type' => MeetingRhythmType::MonthlyNthWeekday,
            'interval_weeks' => null,
            'starts_on' => null,
            'weekday' => 1,
            'week_of_month' => 3,
            'day_of_month' => null,
            'rhythm_note' => null,
            'updated_by' => null,
        ];
    }

    public function noteOnly(?string $note = 'Termin nach Romanveröffentlichung'): static
    {
        return $this->state(fn () => [
            'rhythm_type' => MeetingRhythmType::NoteOnly,
            'interval_weeks' => null,
            'starts_on' => null,
            'weekday' => null,
            'week_of_month' => null,
            'day_of_month' => null,
            'rhythm_note' => $note,
        ]);
    }

    public function monthlyDayOfMonth(int $dayOfMonth = 3): static
    {
        return $this->state(fn () => [
            'rhythm_type' => MeetingRhythmType::MonthlyDayOfMonth,
            'interval_weeks' => null,
            'starts_on' => null,
            'weekday' => null,
            'week_of_month' => null,
            'day_of_month' => $dayOfMonth,
        ]);
    }

    public function everyNWeeks(int $intervalWeeks = 2, string $startsOn = '2026-05-14'): static
    {
        return $this->state(fn () => [
            'rhythm_type' => MeetingRhythmType::EveryNWeeks,
            'interval_weeks' => $intervalWeeks,
            'starts_on' => $startsOn,
            'weekday' => null,
            'week_of_month' => null,
            'day_of_month' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
