<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Mission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('start mission creates record', function (): void {
    Carbon::setTestNow('2025-01-01 12:00:00');
    $user = $this->actingMember();
    $this->actingAs($user);
    $response = $this->postJson('/mission/starten', ['name' => 'Testmission', 'origin' => 'A', 'destination' => 'B', 'travel_duration' => 10, 'mission_duration' => 20]);
    $response->assertOk()->assertJsonStructure(['message', 'arrival_at', 'mission_ends_at']);
    $this->assertDatabaseHas('missions', ['user_id' => $user->id, 'name' => 'Testmission', 'origin' => 'A', 'destination' => 'B', 'travel_duration' => 10, 'mission_duration' => 20]);
});

test('check mission status completes and rewards points', function (): void {
    Carbon::setTestNow('2025-01-01 12:00:00');
    $user = $this->actingMember();
    $this->actingAs($user);
    $this->postJson('/mission/starten', ['name' => 'Testmission', 'origin' => 'A', 'destination' => 'B', 'travel_duration' => 10, 'mission_duration' => 20]);
    $mission = Mission::first();
    Carbon::setTestNow('2025-01-01 12:01:00');
    $this->postJson('/mission/status-pruefen')->assertOk()->assertJson(['status' => 'completed']);
    $mission->refresh();
    expect($mission->completed)->toBeTrue();
    $this->assertDatabaseCount('user_points', 1);
});
