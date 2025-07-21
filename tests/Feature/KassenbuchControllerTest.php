<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;

class KassenbuchControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied'): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        return $user;
    }

    public function test_add_entry_updates_balance(): void
    {
        $user = $this->actingMember('Kassenwart');
        $this->actingAs($user);

        // initialize kassenstand
        $this->get('/kassenbuch');

        $response = $this->post('/kassenbuch/add-entry', [
            'buchungsdatum' => '2025-01-01',
            'betrag' => 5,
            'beschreibung' => 'Beitrag',
            'typ' => 'einnahme',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('kassenbuch_entries', [
            'beschreibung' => 'Beitrag',
            'betrag' => 5.00,
        ]);
        $this->assertDatabaseHas('kassenstand', [
            'betrag' => 5.00,
        ]);
    }

    public function test_update_payment_updates_membership_since(): void
    {
        $kassenwart = $this->actingMember('Kassenwart');
        $this->actingAs($kassenwart);

        $team = $kassenwart->currentTeam;
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => 'Mitglied']);

        $response = $this->from('/kassenbuch')->put("/kassenbuch/update-payment/{$member->id}", [
            'mitgliedsbeitrag' => 42,
            'bezahlt_bis' => '2025-12-31',
            'mitglied_seit' => '2024-01-01',
        ]);

        $response->assertRedirect('/kassenbuch');

        $member->refresh();
        $this->assertEquals('2025-12-31', $member->bezahlt_bis->format('Y-m-d'));
        $this->assertEquals('2024-01-01', $member->mitglied_seit->format('Y-m-d'));
        $this->assertEquals(42.00, $member->mitgliedsbeitrag);
    }
}
