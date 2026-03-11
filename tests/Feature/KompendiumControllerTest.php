<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\KompendiumRoman;
use App\Models\Reward;
use App\Models\RewardPurchase;
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

    /**
     * Kauft das Kompendium-Reward für einen User.
     */
    private function purchaseKompendiumForUser(User $user): void
    {
        $reward = Reward::where('slug', 'kompendium')->firstOrFail();
        RewardPurchase::create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'cost_baxx' => $reward->cost_baxx,
            'purchased_at' => now(),
        ]);
    }

    public function test_index_hides_search_when_reward_not_purchased(): void
    {
        $user = $this->actingMemberWithPoints(50);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', false);
    }

    public function test_index_shows_search_when_reward_purchased(): void
    {
        $user = $this->actingMemberWithPoints(120);
        $this->purchaseKompendiumForUser($user);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', true);
    }

    public function test_serien_endpoint_returns_only_indexed_serien(): void
    {
        $user = $this->actingMemberWithPoints(150);
        $this->purchaseKompendiumForUser($user);

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
        $this->purchaseKompendiumForUser($user);

        // Keine indexierten Romane

        $response = $this->getJson('/kompendium/serien');

        $response->assertOk();
        $this->assertEmpty($response->json());
    }

    public function test_serien_endpoint_requires_purchased_reward(): void
    {
        $user = $this->actingMemberWithPoints(50);

        $this->getJson('/kompendium/serien')
            ->assertStatus(403)
            ->assertJson(['message' => 'Zugang erfordert den Kauf des Kompendium-Rewards oder AG-Maddraxikon-Mitgliedschaft.']);
    }

    /* --------------------------------------------------------------------- */
    /*  AG Maddraxikon – Zugang ohne 100 Baxx */
    /* --------------------------------------------------------------------- */

    public function test_ag_maddraxikon_member_sees_search_without_purchased_reward(): void
    {
        $user = $this->actingMemberWithPoints(10);
        $this->addUserToAgMaddraxikon($user);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', true);
    }

    public function test_ag_maddraxikon_member_can_use_serien_endpoint(): void
    {
        $user = $this->actingMemberWithPoints(10); // below 100
        $this->addUserToAgMaddraxikon($user);

        $this->getJson('/kompendium/serien')
            ->assertOk();
    }

    public function test_non_ag_member_without_reward_cannot_see_search(): void
    {
        $user = $this->actingMemberWithPoints(50);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', false);
    }

    public function test_user_with_purchased_reward_but_no_ag_can_still_search(): void
    {
        $user = $this->actingMemberWithPoints(100);
        $this->purchaseKompendiumForUser($user);

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

    public function test_user_with_purchased_reward_without_ag_can_use_search_endpoint(): void
    {
        $user = $this->actingMemberWithPoints(150);
        $this->purchaseKompendiumForUser($user);

        // q=t hat min:2 → 422 zeigt, dass der Zugangs-Check (403) bestanden wurde
        $this->getJson('/kompendium/suche?q=t')
            ->assertStatus(422);
    }

    public function test_user_without_ag_and_without_purchased_reward_cannot_use_search(): void
    {
        $user = $this->actingMemberWithPoints(50);

        $this->getJson('/kompendium/suche?q=test')
            ->assertStatus(403)
            ->assertJson(['message' => 'Zugang erfordert den Kauf des Kompendium-Rewards oder AG-Maddraxikon-Mitgliedschaft.']);
    }

    public function test_ag_maddraxikon_member_with_purchased_reward_can_search(): void
    {
        $user = $this->actingMemberWithPoints(150);
        $this->addUserToAgMaddraxikon($user);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', true);
    }

    /* --------------------------------------------------------------------- */
    /*  View-Rendering: data-testid Fix & Script-Block */
    /* --------------------------------------------------------------------- */

    public function test_index_renders_data_testid_on_search_input(): void
    {
        $user = $this->actingMemberWithPoints(150);
        $this->purchaseKompendiumForUser($user);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertSee('data-testid="kompendium-search"', false);
    }

    public function test_index_renders_search_script_with_correct_selector(): void
    {
        $user = $this->actingMemberWithPoints(150);
        $this->purchaseKompendiumForUser($user);

        $response = $this->get('/kompendium');

        $response->assertOk();
        // Prüfe, dass der data-testid Selektor im Markup vorhanden ist
        $response->assertSee('data-testid="kompendium-search"', false);
        // Prüfe, dass das Config-Element für das externe JS-Modul vorhanden ist
        $response->assertSee('id="kompendium-config"', false);
    }

    public function test_index_renders_script_when_search_allowed(): void
    {
        $user = $this->actingMemberWithPoints(150);
        $this->purchaseKompendiumForUser($user);

        $response = $this->get('/kompendium');

        $response->assertOk();
        // Das Kompendium-Config-Element muss gerendert werden (JS-Modul liest Daten daraus)
        $response->assertSee('id="kompendium-config"', false);
    }

    public function test_index_does_not_render_script_when_search_not_allowed(): void
    {
        $user = $this->actingMemberWithPoints(50);

        $response = $this->get('/kompendium');

        $response->assertOk();
        // Kein Kompendium-Config-Element, da Suche nicht erlaubt
        $response->assertDontSee('id="kompendium-config"', false);
    }

    public function test_ag_maddraxikon_member_with_low_points_gets_search_script(): void
    {
        $user = $this->actingMemberWithPoints(10);
        $this->addUserToAgMaddraxikon($user);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', true);
        // Config-Element muss gerendert werden, da showSearch = true
        $response->assertSee('id="kompendium-config"', false);
    }

    public function test_index_shows_no_romane_message_when_empty(): void
    {
        $user = $this->actingMemberWithPoints(150);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertSee('Aktuell sind keine Romane für die Suche indexiert.');
    }

    public function test_index_shows_indexed_romane_summary(): void
    {
        $user = $this->actingMemberWithPoints(150);

        // Erstelle indexierten Roman mit Zyklus
        KompendiumRoman::create([
            'dateiname' => '001 - Test.txt',
            'dateipfad' => 'romane/maddrax/001 - Test.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Test',
            'zyklus' => 'Euree',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
        ]);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertSee('Aktuell sind die folgenden Romane für die Suche indexiert:');
        // Zusammengefasste \u00dcbersicht: Serienname im <strong>-Tag und Zyklus in der Beschreibung
        $response->assertSee('<strong>Maddrax</strong>', false);
        $response->assertSee('Euree-Zyklus');
        $response->assertDontSee('Aktuell sind keine Romane für die Suche indexiert.');
    }

    public function test_index_shows_admin_button_for_admin(): void
    {
        $user = $this->actingMember(Role::Admin);
        $user->incrementTeamPoints(150);
        $this->purchaseKompendiumForUser($user);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertSee('Kompendium verwalten');
    }

    public function test_index_hides_admin_button_for_non_admin(): void
    {
        $user = $this->actingMemberWithPoints(150);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertDontSee('Kompendium verwalten');
    }

    public function test_index_shows_purchase_overlay_when_search_not_allowed(): void
    {
        $user = $this->actingMemberWithPoints(50);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertSee('kompendium-purchase-overlay');
    }
}
