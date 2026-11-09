<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\ErrorReporting\ErrorReportRecipientResolver;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(ErrorReportRecipientResolverTestFixtures::class);

test('it returns only admins from the central members team', function (): void {
    $membersTeam = Team::membersTeam();
    $this->removeSeededAdmins($membersTeam);
    $adminA = User::factory()->create(['email' => 'first-admin@example.com']);
    $adminB = User::factory()->create(['email' => 'SECOND-ADMIN@example.com']);
    $member = User::factory()->create(['email' => 'member@example.com']);
    $otherTeamAdmin = User::factory()->create(['email' => 'other-admin@example.com']);
    $otherTeam = Team::factory()->create(['name' => 'Andere Arbeitsgruppe']);
    $membersTeam->users()->attach($adminA, ['role' => Role::Admin->value]);
    $membersTeam->users()->attach($adminB, ['role' => Role::Admin->value]);
    $membersTeam->users()->attach($member, ['role' => Role::Mitglied->value]);
    $otherTeam->users()->attach($otherTeamAdmin, ['role' => Role::Admin->value]);
    expect(app(ErrorReportRecipientResolver::class)->resolve())->toEqualCanonicalizing(['first-admin@example.com', 'SECOND-ADMIN@example.com']);
});

test('it returns an empty list when no admin exists', function (): void {
    $this->removeSeededAdmins(Team::membersTeam());
    expect(app(ErrorReportRecipientResolver::class)->resolve())->toBe([]);
});
