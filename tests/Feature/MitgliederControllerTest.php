<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function test_index_sorts_members_by_role_desc(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();

        $vorstand = User::factory()->create(['name' => 'Victor Vorstand', 'current_team_id' => $team->id]);
        $team->users()->attach($vorstand, ['role' => 'Vorstand']);

        $kassenwart = User::factory()->create(['name' => 'Karl Kass', 'current_team_id' => $team->id]);
        $team->users()->attach($kassenwart, ['role' => 'Kassenwart']);

        $ehren = User::factory()->create(['name' => 'Erika Ehren', 'current_team_id' => $team->id]);
        $team->users()->attach($ehren, ['role' => 'Ehrenmitglied']);

        $acting = $this->actingMember('Mitglied');
        $acting->update(['name' => 'Aaron Actor']);
        $this->actingAs($acting);

        $response = $this->get('/mitglieder?sort=role&dir=desc');
        $response->assertOk();

        $members = $response->viewData('members');
        $roles = $members->pluck('membership.role')->all();

        $this->assertSame([
            'Vorstand',
            'Mitglied',
            'Kassenwart',
            'Ehrenmitglied',
            'Admin',
        ], $roles);
    }

    public function test_index_sorts_members_by_nachname_asc(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();

        $a = User::factory()->create([
            'name' => 'Anna Alpha',
            'vorname' => 'Anna',
            'nachname' => 'Alpha',
            'current_team_id' => $team->id
        ]);
        $team->users()->attach($a, ['role' => 'Ehrenmitglied']);

        $z = User::factory()->create([
            'name' => 'Zara Zulu',
            'vorname' => 'Zara',
            'nachname' => 'Zulu',
            'current_team_id' => $team->id
        ]);
        $team->users()->attach($z, ['role' => 'Kassenwart']);

        $acting = $this->actingMember('Mitglied');
        $acting->update([
            'name' => 'Mike Member',
            'vorname' => 'Mike',
            'nachname' => 'Member'
        ]);
        $this->actingAs($acting);

        $response = $this->get('/mitglieder?sort=nachname&dir=asc');
        $response->assertOk();

        $members = $response->viewData('members');
        $names = $members->pluck('name')->all();

        $this->assertSame([
            'Anna Alpha',
            'Holger Ehrmann',
            'Mike Member',
            'Zara Zulu',
        ], $names);
    }

    public function test_index_sorts_members_by_last_activity_desc(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();

        $recent = User::factory()->create(['name' => 'Ralf Recent', 'current_team_id' => $team->id]);
        $team->users()->attach($recent, ['role' => 'Mitglied']);

        $older = User::factory()->create(['name' => 'Olaf Old', 'current_team_id' => $team->id]);
        $team->users()->attach($older, ['role' => 'Mitglied']);

        $older->forceFill(['last_activity' => now()->subMinutes(10)->timestamp])->save();
        $recent->forceFill(['last_activity' => now()->timestamp])->save();

        $this->actingAs($this->actingMember('Mitglied'));

        $response = $this->get('/mitglieder?sort=last_activity&dir=desc');
        $response->assertOk();

        $members = $response->viewData('members');
        $names = $members->pluck('name')
            ->filter(fn ($name) => in_array($name, ['Ralf Recent', 'Olaf Old']))
            ->values()
            ->all();

        $this->assertSame(['Ralf Recent', 'Olaf Old'], $names);
    }

    public function test_index_falls_back_to_nachname_on_invalid_sort(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();

        $first = User::factory()->create([
            'name' => 'Alice First',
            'vorname' => 'Alice',
            'nachname' => 'First',
            'current_team_id' => $team->id
        ]);
        $team->users()->attach($first, ['role' => 'Mitglied']);

        $second = User::factory()->create([
            'name' => 'Bob Second',
            'vorname' => 'Bob',
            'nachname' => 'Second',
            'current_team_id' => $team->id
        ]);
        $team->users()->attach($second, ['role' => 'Mitglied']);

        $acting = $this->actingMember('Mitglied');
        $acting->update([
            'name' => 'Charlie Current',
            'vorname' => 'Charlie',
            'nachname' => 'Current'
        ]);
        $this->actingAs($acting);

        $response = $this->get('/mitglieder?sort=foo&dir=desc');
        $response->assertOk();
        $this->assertSame('nachname', $response->viewData('sortBy'));

        $members = $response->viewData('members');
        $names = $members->pluck('name')->all();

        $this->assertSame([
            'Bob Second',
            'Alice First',
            'Holger Ehrmann',
            'Charlie Current',
        ], $names);
    }

    public function test_filter_shows_only_online_members(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();

        $online = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($online, ['role' => 'Mitglied']);

        $offline = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($offline, ['role' => 'Mitglied']);

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $online->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($online);

        $response = $this->get('/mitglieder?filters[]=online');
        $response->assertOk();

        $members = $response->viewData('members');
        $this->assertCount(1, $members);
        $this->assertTrue($members->contains('id', $online->id));
    }
}
