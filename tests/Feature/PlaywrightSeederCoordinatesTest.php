<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\TodoPlaywrightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('playwright seeder populates coordinates for map', function (): void {
    $this->seed(TodoPlaywrightSeeder::class);
    $admin = User::firstWhere('email', 'info@maddraxikon.com');
    $member = User::firstWhere('email', 'playwright-member@example.com');
    $mapMember = User::firstWhere('email', 'playwright-map-locked@example.com');
    $team = Team::membersTeam();
    expect($admin)->not->toBeNull()
        ->and($member)->not->toBeNull()
        ->and($mapMember)->not->toBeNull()
        ->and($team)->not->toBeNull()
        ->and((float) $admin->lat)->toEqualWithDelta(48.137154, 1.0E-6)
        ->and((float) $admin->lon)->toEqualWithDelta(11.576124, 1.0E-6)
        ->and((float) $member->lat)->toEqualWithDelta(50.9767, 0.0001)
        ->and((float) $member->lon)->toEqualWithDelta(6.8868, 0.0001)
        ->and((float) $mapMember->lat)->toEqualWithDelta(50.9787, 0.0001)
        ->and((float) $mapMember->lon)->toEqualWithDelta(11.0328, 0.0001);
    $this->assertDatabaseMissing('user_points', ['user_id' => $mapMember->id]);
    $this->assertDatabaseMissing('reward_purchases', ['user_id' => $mapMember->id]);
});
