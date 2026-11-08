<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\DashboardSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('dashboard sample seeder sets coordinates', function (): void {
    Cache::forget(Team::MEMBERS_TEAM_CACHE_KEY);
    Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
    $this->seed(DashboardSampleSeeder::class);
    $sampleUsers = User::whereIn('email', ['alex.beispiel@example.com', 'bianca.beispiel@example.com', 'chris.beispiel@example.com'])->get();
    expect($sampleUsers)->toHaveCount(3);
    $membersTeam = Team::membersTeam();
    expect($membersTeam)->not->toBeNull();
    $membersTeam->loadMissing('users');
    expect($sampleUsers->every(fn (User $user) => $membersTeam->users->contains($user)))->toBeTrue();
    foreach ($sampleUsers as $user) {
        expect($user->lat)->not->toBeNull()
            ->and($user->lon)->not->toBeNull();
    }
});
