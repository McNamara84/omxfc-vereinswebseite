<?php

namespace Tests\Feature;

use App\Enums\AuktionsStatus;
use App\Enums\Role;
use App\Models\Auktion;
use App\Models\AuktionGebot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class AuktionAnzeigeTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.testing_minimal_layout', true);
    }

    public function test_member_sees_active_and_archived_auctions_on_index(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $aktuelleAuktion = Auktion::factory()->create([
            'titel' => 'Signiertes Romanpaket',
            'status' => AuktionsStatus::Laufend,
        ]);
        $archivAuktion = Auktion::factory()->create([
            'titel' => 'Original-Cover-Art',
            'status' => AuktionsStatus::Verkauft,
            'verkauft_at' => now(),
        ]);

        $bieter = $this->createUserWithRole(Role::Ehrenmitglied);
        $verkaufsGebot = AuktionGebot::factory()->for($archivAuktion)->for($bieter)->create([
            'bieter_name' => 'Archiv Bieter',
            'betrag_cent' => 4200,
        ]);
        $archivAuktion->update([
            'verkauft_gebot_id' => $verkaufsGebot->id,
            'verkauft_an_user_id' => $bieter->id,
        ]);

        $response = $this->withoutVite()->actingAs($member)->get(route('auktionen.index'));

        $response->assertOk();
        $response->assertSee('Auktionen');
        $response->assertSee('Signiertes Romanpaket');
        $response->assertSee('Original-Cover-Art');
        $response->assertSee('Archiv');
        $response->assertSee('Verkauft an Archiv Bieter für 42,00 €');
        $response->assertSee(route('auktionen.show', $aktuelleAuktion));
        $response->assertSee('Nächstes Mindestgebot');

        $geladeneAuktion = $response->viewData('aktiveAuktionen')->firstWhere('id', $aktuelleAuktion->id);

        $this->assertNotNull($geladeneAuktion);
        $this->assertTrue($geladeneAuktion->relationLoaded('hoechstgebotRelation'));
        $this->assertFalse($geladeneAuktion->relationLoaded('verkauftesGebot'));
        $this->assertFalse($geladeneAuktion->relationLoaded('gebote'));

        $geladeneArchivAuktion = $response->viewData('archivierteAuktionen')->firstWhere('id', $archivAuktion->id);

        $this->assertNotNull($geladeneArchivAuktion);
        $this->assertTrue($geladeneArchivAuktion->relationLoaded('verkauftesGebot'));
    }

    public function test_auction_detail_page_shows_complete_bid_history(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $auktion = Auktion::factory()->create([
            'titel' => 'MADDRAX Sammlerbox',
        ]);

        $firstBidder = $this->createUserWithRole(Role::Mitglied);
        $secondBidder = $this->createUserWithRole(Role::Mitwirkender);

        AuktionGebot::factory()->for($auktion)->for($firstBidder)->create([
            'bieter_name' => 'Erster Bieter',
            'betrag_cent' => 1100,
            'created_at' => now()->subMinutes(4),
            'updated_at' => now()->subMinutes(4),
        ]);
        AuktionGebot::factory()->for($auktion)->for($secondBidder)->create([
            'bieter_name' => 'Zweiter Bieter',
            'betrag_cent' => 1400,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $response = $this->withoutVite()->actingAs($member)->get(route('auktionen.show', $auktion));

        $response->assertOk();
        $response->assertSee('Gebotsverlauf');
        $response->assertSee('Zur Übersicht');
        $response->assertSee('Nächstes Mindestgebot');
        $response->assertSee('Erster Bieter');
        $response->assertSee('11,00 €');
        $response->assertSee('Zweiter Bieter');
        $response->assertSee('14,00 €');
        $response->assertSee('Gebot speichern');

        $geladeneAuktion = $response->viewData('auktion');

        $this->assertTrue($geladeneAuktion->relationLoaded('gebote'));
        $this->assertTrue($geladeneAuktion->relationLoaded('verkauftesGebot'));
        $this->assertFalse($geladeneAuktion->relationLoaded('verkauftAnUser'));
        $this->assertFalse($geladeneAuktion->gebote->first()->relationLoaded('user'));
    }

    public function test_closed_auction_detail_shows_result_and_hides_bid_form(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $gewinner = $this->createUserWithRole(Role::Ehrenmitglied);
        $auktion = Auktion::factory()->create([
            'titel' => 'Vintage Poster',
            'status' => AuktionsStatus::Verkauft,
            'verkauft_at' => now(),
        ]);

        $verkaufsGebot = AuktionGebot::factory()->for($auktion)->for($gewinner)->create([
            'bieter_name' => 'Gewinner Name',
            'betrag_cent' => 5300,
        ]);
        $auktion->update([
            'verkauft_gebot_id' => $verkaufsGebot->id,
            'verkauft_an_user_id' => $gewinner->id,
        ]);

        $response = $this->withoutVite()->actingAs($member)->get(route('auktionen.show', $auktion));

        $response->assertOk();
        $response->assertSee('Zuschlag');
        $response->assertSee('Gewinner Name');
        $response->assertSee('53,00 €');
        $response->assertSee('für 53,00 €');
        $response->assertDontSee('Gebot speichern');

        $geladeneAuktion = $response->viewData('auktion');

        $this->assertTrue($geladeneAuktion->relationLoaded('verkauftesGebot'));
        $this->assertFalse($geladeneAuktion->relationLoaded('verkauftAnUser'));
    }
}
