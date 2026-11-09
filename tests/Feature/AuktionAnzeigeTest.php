<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuktionsStatus;
use App\Enums\Role;
use App\Models\Auktion;
use App\Models\AuktionGebot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('app.testing_minimal_layout', true);
});

test('member sees active and archived auctions on index', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $aktuelleAuktion = Auktion::factory()->create(['titel' => 'Signiertes Romanpaket', 'status' => AuktionsStatus::Laufend]);
    $archivAuktion = Auktion::factory()->create(['titel' => 'Original-Cover-Art', 'status' => AuktionsStatus::Verkauft, 'verkauft_at' => now()]);
    $bieter = $this->createUserWithRole(Role::Ehrenmitglied);
    $verkaufsGebot = AuktionGebot::factory()->for($archivAuktion)->for($bieter)->create(['bieter_name' => 'Archiv Bieter', 'betrag_cent' => 4200]);
    $archivAuktion->update(['verkauft_gebot_id' => $verkaufsGebot->id, 'verkauft_an_user_id' => $bieter->id]);
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
    expect($geladeneAuktion)->not->toBeNull()
        ->and($geladeneAuktion->relationLoaded('hoechstgebotRelation'))->toBeTrue()
        ->and($geladeneAuktion->relationLoaded('verkauftesGebot'))->toBeFalse()
        ->and($geladeneAuktion->relationLoaded('gebote'))->toBeFalse();
    $geladeneArchivAuktion = $response->viewData('archivierteAuktionen')->firstWhere('id', $archivAuktion->id);
    expect($geladeneArchivAuktion)->not->toBeNull()
        ->and($geladeneArchivAuktion->relationLoaded('verkauftesGebot'))->toBeTrue();
});

test('auction detail page shows complete bid history', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create(['titel' => 'MADDRAX Sammlerbox']);
    $firstBidder = $this->createUserWithRole(Role::Mitglied);
    $secondBidder = $this->createUserWithRole(Role::Mitwirkender);
    AuktionGebot::factory()->for($auktion)->for($firstBidder)->create(['bieter_name' => 'Erster Bieter', 'betrag_cent' => 1100, 'created_at' => now()->subMinutes(4), 'updated_at' => now()->subMinutes(4)]);
    AuktionGebot::factory()->for($auktion)->for($secondBidder)->create(['bieter_name' => 'Zweiter Bieter', 'betrag_cent' => 1400, 'created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()]);
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
    $html = $response->getContent();
    expect($html)->toMatch('/<input[^>]*id="betrag"[^>]*type="text"[^>]*inputmode="decimal"[^>]*>/i');
    $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="betrag"[^>]*type="number"[^>]*>/i', $html);
    $geladeneAuktion = $response->viewData('auktion');
    expect($geladeneAuktion->relationLoaded('gebote'))->toBeTrue()
        ->and($geladeneAuktion->relationLoaded('verkauftesGebot'))->toBeTrue()
        ->and($geladeneAuktion->relationLoaded('verkauftAnUser'))->toBeFalse()
        ->and($geladeneAuktion->gebote->first()->relationLoaded('user'))->toBeFalse();
});

test('closed auction detail shows result and hides bid form', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $gewinner = $this->createUserWithRole(Role::Ehrenmitglied);
    $auktion = Auktion::factory()->create(['titel' => 'Vintage Poster', 'status' => AuktionsStatus::Verkauft, 'verkauft_at' => now()]);
    $verkaufsGebot = AuktionGebot::factory()->for($auktion)->for($gewinner)->create(['bieter_name' => 'Gewinner Name', 'betrag_cent' => 5300]);
    $auktion->update(['verkauft_gebot_id' => $verkaufsGebot->id, 'verkauft_an_user_id' => $gewinner->id]);
    $response = $this->withoutVite()->actingAs($member)->get(route('auktionen.show', $auktion));
    $response->assertOk();
    $response->assertSee('Zuschlag');
    $response->assertSee('Gewinner Name');
    $response->assertSee('53,00 €');
    $response->assertSee('für 53,00 €');
    $response->assertDontSee('Gebot speichern');
    $geladeneAuktion = $response->viewData('auktion');
    expect($geladeneAuktion->relationLoaded('verkauftesGebot'))->toBeTrue()
        ->and($geladeneAuktion->relationLoaded('verkauftAnUser'))->toBeFalse();
});
