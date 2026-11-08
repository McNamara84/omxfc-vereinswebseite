<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('pivot role is accessible', function (): void {
    $team = Team::factory()->create();
    $team->users()->detach();
    $member = User::factory()->create();
    $applicant = User::factory()->create();
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $team->users()->attach($applicant, ['role' => Role::Anwaerter->value]);
    $members = $team->activeUsers()->as('pivot')->select('users.id', 'users.name', 'users.plz', 'users.land', 'users.stadt')->withPivot('role')->get();
    expect($members)->toHaveCount(1);
    $retrieved = $members->first();
    expect($retrieved->id)->toBe($member->id)
        ->and($retrieved->pivot->role)->toBe(Role::Mitglied->value);
});
