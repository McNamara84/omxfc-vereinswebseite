<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Kassenstand;
use App\Models\Team;
use App\Services\MembersTeamProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KassenstandControllerTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesUserWithRole;

    public function test_mitglied_kann_kassenstand_sehen(): void
    {
        $this->actingMember();

        $response = $this->get('/kassenstand');

        $response->assertOk();
        $response->assertViewIs('kassenbuch.kassenstand');
    }

    public function test_kassenstand_zeigt_mitgliedsbeitrag(): void
    {
        $this->actingMember(attributes: ['mitgliedsbeitrag' => 36.00]);

        $response = $this->get('/kassenstand');

        $response->assertOk();
        $response->assertSee('Dein Mitgliedsbeitrag');
        $response->assertSee('36,00');
    }

    public function test_kassenstand_zeigt_aktuellen_kassenstand(): void
    {
        $user = $this->actingMember();

        $team = $user->currentTeam;
        Kassenstand::create([
            'team_id' => $team->id,
            'betrag' => 1234.56,
            'letzte_aktualisierung' => now(),
        ]);

        $response = $this->get('/kassenstand');

        $response->assertOk();
        $response->assertSee('Aktueller Kassenstand');
        $response->assertSee('1.234,56');
    }

    public function test_kassenstand_zeigt_keine_verwaltung(): void
    {
        $this->actingMember();

        $response = $this->get('/kassenstand');

        $response->assertOk();
        $response->assertDontSee('Eintrag hinzufügen');
        $response->assertDontSee('Zahlung aktualisieren');
    }

    public function test_vorstand_sieht_kassenstand_wie_mitglied(): void
    {
        $this->actingVorstand();

        $response = $this->get('/kassenstand');

        $response->assertOk();
        $response->assertViewIs('kassenbuch.kassenstand');
        $response->assertSee('Dein Mitgliedsbeitrag');
        $response->assertSee('Aktueller Kassenstand');
    }

    public function test_gast_wird_zum_login_weitergeleitet(): void
    {
        $response = $this->get('/kassenstand');

        $response->assertRedirect('/login');
    }

    public function test_kassenstand_erstellt_initialen_eintrag(): void
    {
        $user = $this->actingMember();

        $this->assertDatabaseMissing('kassenstand', ['team_id' => $user->currentTeam->id]);

        $response = $this->get('/kassenstand');

        $response->assertOk();
        $this->assertDatabaseHas('kassenstand', ['team_id' => $user->currentTeam->id, 'betrag' => 0.00]);
    }

    public function test_kassenstand_uses_members_team_provider(): void
    {
        $team = Team::membersTeam();

        $this->mock(MembersTeamProvider::class, function ($mock) use ($team) {
            $mock->shouldReceive('getMembersTeamOrAbort')->once()->andReturn($team);
        });

        $user = $this->createUserWithRole(Role::Mitglied);
        $this->actingAs($user);

        $this->get('/kassenstand')->assertOk();
    }
}
