<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\AuktionsStatus;
use App\Enums\Role;
use App\Models\Auktion;
use App\Models\AuktionGebot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('next minimum bid uses start amount without existing bids', function (): void {
    $auktion = Auktion::factory()->create(['startbetrag_cent' => 1200, 'mindestschritt_cent' => 200]);
    expect($auktion->naechstesMindestgebotCent())->toBe(1200)
        ->and($auktion->naechstesMindestgebot())->toBe('12,00 €');
});

test('next minimum bid uses highest bid plus minimum step', function (): void {
    $bieter = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create(['startbetrag_cent' => 1200, 'mindestschritt_cent' => 300]);
    AuktionGebot::factory()->for($auktion)->for($bieter)->create(['bieter_name' => $bieter->name, 'betrag_cent' => 2500]);
    expect($auktion->fresh('gebote')->naechstesMindestgebotCent())->toBe(2800)
        ->and($auktion->fresh('gebote')->naechstesMindestgebot())->toBe('28,00 €');
});

test('gebotsverlauf is stably sorted by created at and id', function (): void {
    $bieter = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create();
    $timestamp = now()->startOfSecond();
    $erstesGebot = AuktionGebot::factory()->for($auktion)->for($bieter)->create(['bieter_name' => $bieter->name, 'betrag_cent' => 1200, 'created_at' => $timestamp, 'updated_at' => $timestamp]);
    $zweitesGebot = AuktionGebot::factory()->for($auktion)->for($bieter)->create(['bieter_name' => $bieter->name, 'betrag_cent' => 1400, 'created_at' => $timestamp, 'updated_at' => $timestamp]);
    expect($auktion->fresh()->gebotsverlauf()->pluck('id')->all())->toBe([$erstesGebot->id, $zweitesGebot->id]);
});

test('hoechstgebot relation uses same tie breakers as hoechstgebot method', function (): void {
    $bieter = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create();
    $spaeteresGebotMitNiedrigererId = AuktionGebot::factory()->for($auktion)->for($bieter)->create(['bieter_name' => $bieter->name, 'betrag_cent' => 2500, 'created_at' => now()->addMinute(), 'updated_at' => now()->addMinute()]);
    AuktionGebot::factory()->for($auktion)->for($bieter)->create(['bieter_name' => $bieter->name, 'betrag_cent' => 2500, 'created_at' => now(), 'updated_at' => now()]);
    $frischeAuktion = $auktion->fresh('hoechstgebotRelation');
    expect($frischeAuktion->hoechstgebotRelation)->not->toBeNull()
        ->and($frischeAuktion->hoechstgebotRelation->id)->toBe($spaeteresGebotMitNiedrigererId->id)
        ->and($frischeAuktion->hoechstgebot()?->id)->toBe($spaeteresGebotMitNiedrigererId->id);
});

test('markdown description is sanitized', function (): void {
    $auktion = Auktion::factory()->create(['beschreibung_markdown' => "**Sicher**\n\n![Verstecktes Bild](https://example.com/bild.png)\n\n[Unsicher](javascript:alert('xss'))\n\n[Dokumente](docs/auktion.md?ref=1#start)"]);
    $htmlBeschreibung = $auktion->html_beschreibung;
    expect($htmlBeschreibung)->toContain('<strong>Sicher</strong>')->not->toContain('<img');
    $this->assertStringNotContainsStringIgnoringCase('javascript:', $htmlBeschreibung);
    expect($htmlBeschreibung)->toContain('<a rel="noopener noreferrer">Unsicher</a>')
        ->toContain('<a href="docs/auktion.md?ref=1#start" rel="noopener noreferrer">Dokumente</a>');
});

test('html beschreibung is cached across instances', function (): void {
    config(['cache.default' => 'array']);
    Cache::flush();
    $auktion = Auktion::factory()->create(['beschreibung_markdown' => '**Cached Inhalt**']);
    $first = $auktion->html_beschreibung;
    $cacheKey = sprintf('auktion:%s:html_beschreibung:%s:%s', $auktion->id, $auktion->updated_at->format('Uu'), md5('**Cached Inhalt**'));
    expect(Cache::has($cacheKey))->toBeTrue()
        ->and(Auktion::find($auktion->id)->html_beschreibung)->toBe($first);
});

test('html beschreibung cache key changes after update', function (): void {
    config(['cache.default' => 'array']);
    Cache::flush();
    $auktion = Auktion::factory()->create(['beschreibung_markdown' => 'Erste Version']);
    $initialKey = sprintf('auktion:%s:html_beschreibung:%s:%s', $auktion->id, $auktion->updated_at->format('Uu'), md5('Erste Version'));
    $auktion->html_beschreibung;
    expect(Cache::has($initialKey))->toBeTrue();
    $auktion->update(['beschreibung_markdown' => 'Zweite Version']);
    $auktion->refresh();
    $updatedKey = sprintf('auktion:%s:html_beschreibung:%s:%s', $auktion->id, $auktion->updated_at->format('Uu'), md5('Zweite Version'));
    expect($updatedKey)->not->toBe($initialKey);
    $auktion->html_beschreibung;
    expect(Cache::has($updatedKey))->toBeTrue();
});

test('kann gebote annehmen depends on status', function (AuktionsStatus $status, bool $expected): void {
    $auktion = Auktion::factory()->create(['status' => $status]);
    expect($auktion->kannGeboteAnnehmen())->toBe($expected);
})->with([[AuktionsStatus::Laufend, true], [AuktionsStatus::ZumErsten, true], [AuktionsStatus::ZumZweiten, true], [AuktionsStatus::Verkauft, false], [AuktionsStatus::NichtVerkauft, false]]);
