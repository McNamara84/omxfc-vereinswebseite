<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_chronik_page_shows_heading(): void
    {
        $this->get('/chronik')
            ->assertOk()
            ->assertSee('Chronik des Offiziellen MADDRAX Fanclub e. V.');
    }

    public function test_ehrenmitglieder_page_shows_member_names(): void
    {
        $this->get('/ehrenmitglieder')
            ->assertOk()
            ->assertSee('Ehrenmitglieder')
            ->assertSee('Michael Edelbrock');
    }

    public function test_satzung_page_shows_sections(): void
    {
        $this->get('/satzung')
            ->assertOk()
            ->assertSee('Satzung des Offiziellen MADDRAX Fanclub e.V.')
            ->assertSee('§1 Name', false);
    }

    public function test_mitglied_werden_page_shows_form_fields(): void
    {
        $this->get('/mitglied-werden')
            ->assertOk()
            ->assertSee('Mitglied werden')
            ->assertSee('Vorname')
            ->assertSee('Nachname');
    }

    public function test_mitglied_werden_erfolgreich_page_shows_success_message(): void
    {
        $this->get('/mitglied-werden/erfolgreich')
            ->assertOk()
            ->assertSee('Antrag erfolgreich eingereicht');
    }

    public function test_mitglied_werden_bestaetigt_page_shows_confirmation_message(): void
    {
        $this->get('/mitglied-werden/bestaetigt')
            ->assertOk()
            ->assertSee('Vielen Dank für deine Bestätigung!');
    }

    public function test_spenden_page_shows_heading_and_description(): void
    {
        $this->get('/spenden')
            ->assertOk()
            ->assertSee('Spenden')
            ->assertSee('Spenden helfen uns bei der Finanzierung der jährlichen Fantreffen sowie der Serverkosten dieser Webseite')
            ->assertSee('kassenwart@maddrax-fanclub.de')
            ->assertSee('Spenden mit PayPal');
    }

    public function test_arbeitsgruppen_page_shows_leader_schedule_and_contact(): void
    {
        $leader = User::factory()->create(['name' => 'Max Mustermann']);
        Team::factory()->create([
            'name' => 'AG Öffentlichkeit',
            'user_id' => $leader->id,
            'personal_team' => false,
            'description' => 'Beschreibung der AG',
            'meeting_schedule' => 'jeden Dienstag',
            'email' => 'ag@example.com',
        ]);

        $this->get('/arbeitsgruppen')
            ->assertOk()
            ->assertSee('AG Öffentlichkeit')
            ->assertSee('Beschreibung der AG')
            ->assertSee('Max Mustermann')
            ->assertSee('jeden Dienstag')
            ->assertSee('ag@example.com');
    }
}

