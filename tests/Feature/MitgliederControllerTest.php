<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;

class MitgliederControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied'): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        return $user;
    }

    public function test_export_csv_requires_proper_role(): void
    {
        $this->actingAs($this->actingMember('Mitglied'));

        $response = $this->from('/mitglieder')->post('/mitglieder/export-csv', [
            'export_fields' => ['name', 'email']
        ]);

        $response->assertRedirect('/mitglieder');
        $response->assertSessionHas('error');
    }

    public function test_export_csv_returns_csv_for_kassenwart(): void
    {
        $user = $this->actingMember('Kassenwart');
        $this->actingAs($user);

        Team::where('name', 'Mitglieder')->first()->users()->attach(
            User::factory()->create(), ['role' => 'Mitglied']
        );

        $response = $this->post('/mitglieder/export-csv', [
            'export_fields' => ['name', 'email']
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Name', $csv);
    }

    public function test_get_all_emails_returns_only_for_privileged_roles(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $team->users()->attach(User::factory()->create(['email' => 'a@a.de']), ['role' => 'Mitglied']);
        $team->users()->attach(User::factory()->create(['email' => 'b@a.de']), ['role' => 'Mitglied']);

        $this->actingAs($this->actingMember('Kassenwart'));

        $response = $this->getJson('/mitglieder/all-emails');
        $response->assertOk();
        $data = $response->json('emails');
        $this->assertStringContainsString('a@a.de', $data);
        $this->assertStringContainsString('b@a.de', $data);

        $this->actingAs($this->actingMember('Mitglied'));
        $this->getJson('/mitglieder/all-emails')->assertStatus(403);
    }

    public function test_higher_rank_user_can_change_member_role(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $board = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($board, ['role' => 'Vorstand']);
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => 'Mitglied']);
        $this->actingAs($board);

        $response = $this->from('/mitglieder')->put("/mitglieder/{$member->id}/role", [
            'role' => 'Ehrenmitglied'
        ]);

        $response->assertRedirect('/mitglieder');
        $this->assertDatabaseHas('team_user', [
            'user_id' => $member->id,
            'role' => 'Ehrenmitglied'
        ]);
    }

    public function test_cannot_assign_role_higher_than_own(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $board = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($board, ['role' => 'Vorstand']);
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => 'Mitglied']);
        $this->actingAs($board);

        $response = $this->from('/mitglieder')->put("/mitglieder/{$member->id}/role", [
            'role' => 'Admin'
        ]);

        $response->assertRedirect('/mitglieder');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('team_user', [
            'user_id' => $member->id,
            'role' => 'Mitglied'
        ]);
    }

    public function test_user_cannot_remove_self(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $board = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($board, ['role' => 'Vorstand']);
        $this->actingAs($board);

        $response = $this->from('/mitglieder')->delete("/mitglieder/{$board->id}");

        $response->assertRedirect('/mitglieder');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $board->id]);
    }

    public function test_higher_rank_user_can_remove_member(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $board = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($board, ['role' => 'Vorstand']);
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => 'Mitglied']);
        $this->actingAs($board);

        $response = $this->from('/mitglieder')->delete("/mitglieder/{$member->id}");

        $response->assertRedirect('/mitglieder');
        $this->assertDatabaseMissing('users', ['id' => $member->id]);
        $this->assertDatabaseMissing('team_user', ['user_id' => $member->id]);
    }
}
