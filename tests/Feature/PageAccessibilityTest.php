<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;

class PageAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_are_accessible(): void
    {
        $urls = [
            '/',
            '/satzung',
            '/chronik',
            '/ehrenmitglieder',
            '/termine',
            '/arbeitsgruppen',
            '/mitglied-werden',
            '/spenden',
            '/impressum',
            '/datenschutz',
            '/changelog',
            '/mitglied-werden/erfolgreich',
            '/mitglied-werden/bestaetigt',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_home_page_shows_correct_member_count(): void
    {
        $team = Team::membersTeam();

        $team->users()->attach(User::factory()->create(), ['role' => 'Mitglied']);
        $team->users()->attach(User::factory()->create(), ['role' => 'Anwärter']);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('2');
    }

    public function test_impressum_page_shows_contact_information(): void
    {
        $this->get('/impressum')
            ->assertOk()
            ->assertSee('info@maddrax-fanclub.de')
            ->assertSee('Angaben gemäß §5 TMG');
    }

    public function test_datenschutz_page_displays_data_protection_details(): void
    {
        $this->get('/datenschutz')
            ->assertOk()
            ->assertSee('Datenschutzerklärung')
            ->assertSee('Verantwortlicher')
            ->assertSee('Zweck der Verarbeitung');
    }

    public function test_spenden_page_contains_paypal_form(): void
    {
        $this->get('/spenden')
            ->assertOk()
            ->assertSee('kassenwart@maddrax-fanclub.de')
            ->assertSee('paypal.com');
    }

    public function test_arbeitsgruppen_page_shows_ag_info(): void
    {
        $leader = User::factory()->create();
        Team::factory()->create([
            'name' => 'AG Test',
            'user_id' => $leader->id,
            'personal_team' => false,
            'description' => 'Beschreibung der AG',
        ]);

        $this->get('/arbeitsgruppen')
            ->assertOk()
            ->assertSee('AG Test')
            ->assertSee('Beschreibung der AG');
    }
}
