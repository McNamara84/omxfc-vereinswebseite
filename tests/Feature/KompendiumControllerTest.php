<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\KompendiumRoman;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KompendiumControllerTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesUserWithRole;

    /**
     * Erstellt eine AG Maddraxikon und fügt den User als Mitglied hinzu.
     */
    private function addUserToAgMaddraxikon(User $user): Team
    {
        $ag = Team::factory()->create([
            'name' => 'AG Maddraxikon',
            'personal_team' => false,
        ]);

        $ag->users()->attach($user, ['role' => Role::Mitglied->value]);

        $user->refresh();

        return $ag;
    }

    public function test_index_hides_search_when_points_insufficient(): void
    {
        $user = $this->actingMemberWithPoints(50);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', false);
        $response->assertViewHas('userPoints', 50);
    }

    public function test_index_shows_search_when_enough_points(): void
    {
        $user = $this->actingMemberWithPoints(120);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', true);
        $response->assertViewHas('userPoints', 120);
    }

    public function test_serien_endpoint_returns_only_indexed_serien(): void
    {
        $user = $this->actingMemberWithPoints(150);

        // Erstelle indexierte Romane für verschiedene Serien
        KompendiumRoman::create([
            'dateiname' => '001 - Test.txt',
            'dateipfad' => 'romane/maddrax/001 - Test.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Test',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
        ]);

        KompendiumRoman::create([
            'dateiname' => '001 - Mars.txt',
            'dateipfad' => 'romane/missionmars/001 - Mars.txt',
            'serie' => 'missionmars',
            'roman_nr' => 1,
            'titel' => 'Mars',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
        ]);

        // Erstelle einen nicht-indexierten Roman (sollte NICHT erscheinen)
        KompendiumRoman::create([
            'dateiname' => '001 - Hardcover.txt',
            'dateipfad' => 'romane/hardcovers/001 - Hardcover.txt',
            'serie' => 'hardcovers',
            'roman_nr' => 1,
            'titel' => 'Hardcover',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'hochgeladen', // nicht indexiert!
        ]);

        $response = $this->getJson('/kompendium/serien');

        $response->assertOk();

        $serien = $response->json();

        // Nur maddrax und missionmars sollten zurückgegeben werden
        $this->assertArrayHasKey('maddrax', $serien);
        $this->assertArrayHasKey('missionmars', $serien);
        $this->assertArrayNotHasKey('hardcovers', $serien);

        // Prüfe die Anzeigenamen
        $this->assertEquals('Maddrax - Die dunkle Zukunft der Erde', $serien['maddrax']);
        $this->assertEquals('Mission Mars', $serien['missionmars']);
    }

    public function test_serien_endpoint_returns_empty_when_no_indexed_romane(): void
    {
        $user = $this->actingMemberWithPoints(150);

        // Keine indexierten Romane

        $response = $this->getJson('/kompendium/serien');

        $response->assertOk();
        $this->assertEmpty($response->json());
    }

    public function test_serien_endpoint_requires_enough_points(): void
    {
        $user = $this->actingMemberWithPoints(50); // below 100

        $this->getJson('/kompendium/serien')
            ->assertStatus(403)
            ->assertJson(['message' => 'Zugang erfordert mindestens 100 Punkte oder AG-Maddraxikon-Mitgliedschaft (du hast 50 Punkte).']);
    }

    /* --------------------------------------------------------------------- */
    /*  AG Maddraxikon – Zugang ohne 100 Baxx */
    /* --------------------------------------------------------------------- */

    public function test_ag_maddraxikon_member_sees_search_without_enough_points(): void
    {
        $user = $this->actingMemberWithPoints(10);
        $this->addUserToAgMaddraxikon($user);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', true);
        $response->assertViewHas('userPoints', 10);
    }

    public function test_ag_maddraxikon_member_can_use_serien_endpoint(): void
    {
        $user = $this->actingMemberWithPoints(10); // below 100
        $this->addUserToAgMaddraxikon($user);

        $this->getJson('/kompendium/serien')
            ->assertOk();
    }

    public function test_non_ag_member_without_enough_points_cannot_see_search(): void
    {
        $user = $this->actingMemberWithPoints(50);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', false);
    }

    public function test_user_with_100_points_but_no_ag_can_still_search(): void
    {
        $user = $this->actingMemberWithPoints(100);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', true);
    }

    /* --------------------------------------------------------------------- */
    /*  AG Maddraxikon – Zugang auf /kompendium/suche (AJAX) */
    /* --------------------------------------------------------------------- */

    public function test_ag_maddraxikon_member_can_use_search_endpoint(): void
    {
        $user = $this->actingMemberWithPoints(10); // below 100
        $this->addUserToAgMaddraxikon($user);

        // q=t hat min:2 → 422 zeigt, dass der Zugangs-Check (403) bestanden wurde
        $this->getJson('/kompendium/suche?q=t')
            ->assertStatus(422);
    }

    public function test_user_with_enough_points_without_ag_can_use_search_endpoint(): void
    {
        $user = $this->actingMemberWithPoints(150);

        // q=t hat min:2 → 422 zeigt, dass der Zugangs-Check (403) bestanden wurde
        $this->getJson('/kompendium/suche?q=t')
            ->assertStatus(422);
    }

    public function test_user_without_ag_and_without_enough_points_cannot_use_search(): void
    {
        $user = $this->actingMemberWithPoints(50);

        $this->getJson('/kompendium/suche?q=test')
            ->assertStatus(403)
            ->assertJson(['message' => 'Zugang erfordert mindestens 100 Punkte oder AG-Maddraxikon-Mitgliedschaft (du hast 50 Punkte).']);
    }

    public function test_ag_maddraxikon_member_with_enough_points_can_search(): void
    {
        $user = $this->actingMemberWithPoints(150);
        $this->addUserToAgMaddraxikon($user);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', true);
        $response->assertViewHas('userPoints', 150);
    }
}
