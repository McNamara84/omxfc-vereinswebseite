<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\TodoStatus;
use App\Models\Mission;
use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\User;
use App\Models\UserPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\CreatesUserWithRole;

trait BaxxWorkflowTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function todo(User $creator, array $attributes = []): Todo
    {
        return Todo::create([...['team_id' => Team::membersTeam()->id, 'created_by' => $creator->id, 'title' => 'Gemeinsame Challenge', 'points' => 7, 'status' => TodoStatus::Open, 'category_id' => TodoCategory::create(['name' => 'Tests', 'slug' => 'tests'])->id], ...$attributes]);
    }

    private function failNextBooking(): void
    {
        UserPoint::creating(fn () => throw new RuntimeException('Buchung fehlgeschlagen'));
    }

    private function mission(User $user): Mission
    {
        return Mission::create(['user_id' => $user->id, 'name' => 'Testmission', 'origin' => 'Waashton', 'destination' => 'Salem', 'travel_duration' => 10, 'mission_duration' => 10, 'started_at' => now()->subMinute(), 'arrival_at' => now()->subSeconds(50), 'mission_ends_at' => now()->subSeconds(40), 'completed' => false]);
    }
}
