<?php

namespace Tests\Feature;

use App\Models\KompendiumRoman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KompendiumControllerTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesUserWithRole;

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
}
