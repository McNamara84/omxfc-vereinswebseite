<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class MitgliederControllerTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_export_csv_requires_proper_role(): void
    {
        $this->actingMember(Role::Mitglied);

        $response = $this->from('/mitglieder')->post('/mitglieder/export-csv', [
            'export_fields' => ['name', 'email'],
        ]);

        $response->assertStatus(403);
    }

    public function test_export_csv_returns_csv_for_kassenwart(): void
    {
        $this->actingKassenwart();

        Team::membersTeam()->users()->attach(
            User::factory()->create(), ['role' => Role::Mitglied->value]
        );

        $response = $this->post('/mitglieder/export-csv', [
            'export_fields' => ['name', 'email'],
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Name', $csv);
    }

    public function test_csv_export_roundtrips_quotes_backslashes_multiline_text_and_empty_fields(): void
    {
        $this->actingKassenwart();
        $member = User::factory()->create([
            'vorname' => 'Jörg, \\"Leser"\\',
            'nachname' => "Müller\nZweite Zeile",
            'strasse' => 'C:\\Verein\\',
            'hausnummer' => '',
        ]);
        Team::membersTeam()->users()->attach($member, ['role' => Role::Mitglied->value]);

        $response = $this->post('/mitglieder/export-csv', ['export_fields' => ['name', 'email', 'adresse']]);
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $headers = fgetcsv($stream, escape: '');
        $matched = null;
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            if (($row[3] ?? null) === $member->email) {
                $matched = array_combine($headers, $row);
            }
        }
        fclose($stream);

        $this->assertNotNull($matched);
        $this->assertSame($member->vorname, $matched['Vorname']);
        $this->assertSame($member->nachname, $matched['Nachname']);
        $this->assertSame($member->strasse, $matched['Straße']);
        $this->assertSame('', $matched['Hausnummer']);
    }

    public function test_get_all_emails_returns_only_for_privileged_roles(): void
    {
        $team = Team::membersTeam();
        $team->users()->attach(User::factory()->create(['email' => 'a@a.de']), ['role' => Role::Mitglied->value]);
        $team->users()->attach(User::factory()->create(['email' => 'b@a.de']), ['role' => Role::Mitglied->value]);

        $this->actingAs($this->actingMember('Kassenwart'));

        $response = $this->getJson('/mitglieder/all-emails');
        $response->assertOk();
        $data = $response->json('emails');
        $this->assertStringContainsString('a@a.de', $data);
        $this->assertStringContainsString('b@a.de', $data);
        collect(explode('; ', $data))
            ->each(fn (string $email) => expect($email)->toBeEmail());

        $this->actingAs($this->actingMember('Mitglied'));
        $this->getJson('/mitglieder/all-emails')->assertStatus(403);
    }

    public function test_index_renders_copy_email_button_only_for_privileged_roles(): void
    {
        $team = Team::membersTeam();
        $team->users()->attach(User::factory()->create(['email' => 'copyme@example.test']), ['role' => Role::Mitglied->value]);

        $this->actingAs($this->actingMember('Kassenwart'));
        $this->get('/mitglieder')
            ->assertOk()
            ->assertSee('data-copy-email', false);

        $this->actingAs($this->actingMember('Mitglied'));
        $this->get('/mitglieder')
            ->assertOk()
            ->assertDontSee('data-copy-email', false);
    }

    public function test_higher_rank_user_can_change_member_role(): void
    {
        $team = Team::membersTeam();
        $board = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($board, ['role' => Role::Vorstand->value]);
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);
        $this->actingAs($board);

        $response = $this->from('/mitglieder')->put("/mitglieder/{$member->id}/role", [
            'role' => Role::Ehrenmitglied->value,
        ]);

        $response->assertRedirect('/mitglieder');
        $this->assertDatabaseHas('team_user', [
            'user_id' => $member->id,
            'role' => Role::Ehrenmitglied->value,
        ]);
    }

    public function test_member_cannot_change_member_role(): void
    {
        $team = Team::membersTeam();
        $acting = $this->actingMember('Mitglied');
        $this->actingAs($acting);
        $target = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($target, ['role' => Role::Mitglied->value]);

        $response = $this->from('/mitglieder')->put("/mitglieder/{$target->id}/role", [
            'role' => Role::Ehrenmitglied->value,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_assign_role_higher_than_own(): void
    {
        $team = Team::membersTeam();
        $board = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($board, ['role' => Role::Vorstand->value]);
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);
        $this->actingAs($board);

        $response = $this->from('/mitglieder')->put("/mitglieder/{$member->id}/role", [
            'role' => Role::Admin->value,
        ]);

        $response->assertRedirect('/mitglieder');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('team_user', [
            'user_id' => $member->id,
            'role' => Role::Mitglied->value,
        ]);
    }

    public function test_user_cannot_remove_self(): void
    {
        $team = Team::membersTeam();
        $board = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($board, ['role' => Role::Vorstand->value]);
        $this->actingAs($board);

        $response = $this->from('/mitglieder')->delete("/mitglieder/{$board->id}");

        $response->assertRedirect('/mitglieder');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $board->id]);
    }

    public function test_member_cannot_remove_member(): void
    {
        $team = Team::membersTeam();
        $acting = $this->actingMember('Mitglied');
        $this->actingAs($acting);
        $target = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($target, ['role' => Role::Mitglied->value]);

        $response = $this->from('/mitglieder')->delete("/mitglieder/{$target->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_higher_rank_user_can_remove_member(): void
    {
        $team = Team::membersTeam();
        $board = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($board, ['role' => Role::Vorstand->value]);
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);
        $this->actingAs($board);

        $response = $this->from('/mitglieder')->delete("/mitglieder/{$member->id}");

        $response->assertRedirect('/mitglieder');
        $this->assertDatabaseMissing('users', ['id' => $member->id]);
        $this->assertDatabaseMissing('team_user', ['user_id' => $member->id]);
    }
}
