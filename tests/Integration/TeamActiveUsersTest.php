<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

covers(Team::class);

test('active users excludes applicants', function (): void {
    $team = Team::factory()->create();
    $team->users()->detach();
    $member = User::factory()->create();
    $applicant = User::factory()->create();
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $team->users()->attach($applicant, ['role' => Role::Anwaerter->value]);
    $active = $team->activeUsers()->get();
    expect($active->contains($member))->toBeTrue()
        ->and($active->contains($applicant))->toBeFalse();
});
