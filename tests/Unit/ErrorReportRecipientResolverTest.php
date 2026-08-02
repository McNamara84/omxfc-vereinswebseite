<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\ErrorReporting\ErrorReportRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErrorReportRecipientResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_admins_from_the_central_members_team(): void
    {
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

        $this->assertEqualsCanonicalizing(
            ['first-admin@example.com', 'SECOND-ADMIN@example.com'],
            app(ErrorReportRecipientResolver::class)->resolve(),
        );
    }

    public function test_it_returns_an_empty_list_when_no_admin_exists(): void
    {
        $this->removeSeededAdmins(Team::membersTeam());

        $this->assertSame([], app(ErrorReportRecipientResolver::class)->resolve());
    }

    private function removeSeededAdmins(Team $team): void
    {
        DB::table('team_user')
            ->where('team_id', $team->id)
            ->where('role', Role::Admin->value)
            ->delete();
    }
}
