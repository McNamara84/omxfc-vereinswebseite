<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\WebsiteFeedback\FeedbackRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeedbackRecipientResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_deduplicated_admin_and_vorstand_addresses_from_the_members_team(): void
    {
        $membersTeam = Team::membersTeam();
        $this->removeSeededManagement($membersTeam);

        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $vorstand = User::factory()->create(['email' => 'BOARD@example.com']);
        $duplicate = User::factory()->create(['email' => 'board@EXAMPLE.com']);
        $kassenwart = User::factory()->create(['email' => 'cash@example.com']);
        $member = User::factory()->create(['email' => 'member@example.com']);
        $invalid = User::factory()->create(['email' => 'keine-mailadresse']);
        $otherTeamAdmin = User::factory()->create(['email' => 'other@example.com']);
        $otherTeam = Team::factory()->create(['name' => 'Andere AG']);

        $membersTeam->users()->attach($admin, ['role' => Role::Admin->value]);
        $membersTeam->users()->attach($vorstand, ['role' => Role::Vorstand->value]);
        $membersTeam->users()->attach($duplicate, ['role' => Role::Vorstand->value]);
        $membersTeam->users()->attach($kassenwart, ['role' => Role::Kassenwart->value]);
        $membersTeam->users()->attach($member, ['role' => Role::Mitglied->value]);
        $membersTeam->users()->attach($invalid, ['role' => Role::Admin->value]);
        $otherTeam->users()->attach($otherTeamAdmin, ['role' => Role::Admin->value]);

        $this->assertEqualsCanonicalizing(
            ['admin@example.com', 'BOARD@example.com'],
            app(FeedbackRecipientResolver::class)->resolve(),
        );
    }

    public function test_it_returns_an_empty_list_without_admin_or_vorstand(): void
    {
        $this->removeSeededManagement(Team::membersTeam());

        $this->assertSame([], app(FeedbackRecipientResolver::class)->resolve());
    }

    private function removeSeededManagement(Team $team): void
    {
        DB::table('team_user')
            ->where('team_id', $team->id)
            ->whereIn('role', [Role::Admin->value, Role::Vorstand->value])
            ->delete();
    }
}
