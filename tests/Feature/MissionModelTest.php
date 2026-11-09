<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('mission can be created with mass assignment', function (): void {
    Carbon::setTestNow('2025-01-01 12:00:00');
    $user = User::factory()->create();
    $mission = Mission::create(['user_id' => $user->id, 'name' => 'Test Mission', 'origin' => 'A', 'destination' => 'B', 'travel_duration' => 10, 'mission_duration' => 20, 'started_at' => Carbon::now(), 'arrival_at' => Carbon::now()->addMinutes(10), 'mission_ends_at' => Carbon::now()->addMinutes(40)]);
    $this->assertDatabaseHas('missions', ['id' => $mission->id, 'user_id' => $user->id, 'name' => 'Test Mission', 'origin' => 'A', 'destination' => 'B', 'travel_duration' => 10, 'mission_duration' => 20, 'completed' => false]);
});

test('mission belongs to user', function (): void {
    $user = User::factory()->create();
    $mission = Mission::create(['user_id' => $user->id, 'name' => 'Test Mission', 'origin' => 'A', 'destination' => 'B', 'travel_duration' => 10, 'mission_duration' => 20]);
    expect($mission->user->is($user))->toBeTrue();
});

test('completed attribute can be updated', function (): void {
    $user = User::factory()->create();
    $mission = Mission::create(['user_id' => $user->id, 'name' => 'Test Mission', 'origin' => 'A', 'destination' => 'B', 'travel_duration' => 10, 'mission_duration' => 20]);
    expect($mission->completed)->toBeFalsy();
    $mission->update(['completed' => true]);
    expect($mission->fresh()->completed)->toBeTrue();
});

test('date attributes are cast to carbon instances', function (): void {
    Carbon::setTestNow('2025-01-01 12:00:00');
    $user = User::factory()->create();
    $mission = Mission::create(['user_id' => $user->id, 'name' => 'Date Mission', 'origin' => 'A', 'destination' => 'B', 'travel_duration' => 5, 'mission_duration' => 10, 'started_at' => Carbon::now(), 'arrival_at' => Carbon::now()->addMinutes(5), 'mission_ends_at' => Carbon::now()->addMinutes(20)]);
    $mission->refresh();
    expect($mission->started_at)->toBeInstanceOf(Carbon::class)
        ->and($mission->arrival_at)->toBeInstanceOf(Carbon::class)
        ->and($mission->mission_ends_at)->toBeInstanceOf(Carbon::class)
        ->and($mission->started_at->equalTo(Carbon::now()))->toBeTrue()
        ->and($mission->arrival_at->equalTo(Carbon::now()->addMinutes(5)))->toBeTrue()
        ->and($mission->mission_ends_at->equalTo(Carbon::now()->addMinutes(20)))->toBeTrue();
});

test('query for active missions only returns uncompleted ones', function (): void {
    $user = User::factory()->create();
    Mission::create(['user_id' => $user->id, 'name' => 'Active Mission', 'origin' => 'A', 'destination' => 'B', 'travel_duration' => 10, 'mission_duration' => 20]);
    Mission::create(['user_id' => $user->id, 'name' => 'Completed Mission', 'origin' => 'A', 'destination' => 'B', 'travel_duration' => 5, 'mission_duration' => 5, 'completed' => true]);
    $activeMissions = Mission::where('user_id', $user->id)->where('completed', false)->get();
    expect($activeMissions)->toHaveCount(1)
        ->and($activeMissions->first()->name)->toEqual('Active Mission');
});
