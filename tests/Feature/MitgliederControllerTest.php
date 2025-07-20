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
}
