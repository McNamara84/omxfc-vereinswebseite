<?php

namespace Tests\Unit;

use App\Enums\AuktionsStatus;
use App\Enums\Role;
use App\Models\Auktion;
use App\Models\AuktionGebot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class AuktionModelTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_next_minimum_bid_uses_start_amount_without_existing_bids(): void
    {
        $auktion = Auktion::factory()->create([
            'startbetrag_cent' => 1200,
            'mindestschritt_cent' => 200,
        ]);

        $this->assertSame(1200, $auktion->naechstesMindestgebotCent());
        $this->assertSame('12,00 €', $auktion->naechstesMindestgebot());
    }

    public function test_next_minimum_bid_uses_highest_bid_plus_minimum_step(): void
    {
        $bieter = $this->createUserWithRole(Role::Mitglied);
        $auktion = Auktion::factory()->create([
            'startbetrag_cent' => 1200,
            'mindestschritt_cent' => 300,
        ]);

        AuktionGebot::factory()->for($auktion)->for($bieter)->create([
            'bieter_name' => $bieter->name,
            'betrag_cent' => 2500,
        ]);

        $this->assertSame(2800, $auktion->fresh('gebote')->naechstesMindestgebotCent());
        $this->assertSame('28,00 €', $auktion->fresh('gebote')->naechstesMindestgebot());
    }

    public function test_gebotsverlauf_is_stably_sorted_by_created_at_and_id(): void
    {
        $bieter = $this->createUserWithRole(Role::Mitglied);
        $auktion = Auktion::factory()->create();
        $timestamp = now()->startOfSecond();

        $erstesGebot = AuktionGebot::factory()->for($auktion)->for($bieter)->create([
            'bieter_name' => $bieter->name,
            'betrag_cent' => 1200,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $zweitesGebot = AuktionGebot::factory()->for($auktion)->for($bieter)->create([
            'bieter_name' => $bieter->name,
            'betrag_cent' => 1400,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $this->assertSame([
            $erstesGebot->id,
            $zweitesGebot->id,
        ], $auktion->fresh()->gebotsverlauf()->pluck('id')->all());
    }

    public function test_hoechstgebot_relation_uses_same_tie_breakers_as_hoechstgebot_method(): void
    {
        $bieter = $this->createUserWithRole(Role::Mitglied);
        $auktion = Auktion::factory()->create();

        $spaeteresGebotMitNiedrigererId = AuktionGebot::factory()->for($auktion)->for($bieter)->create([
            'bieter_name' => $bieter->name,
            'betrag_cent' => 2500,
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ]);
        AuktionGebot::factory()->for($auktion)->for($bieter)->create([
            'bieter_name' => $bieter->name,
            'betrag_cent' => 2500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $frischeAuktion = $auktion->fresh('hoechstgebotRelation');

        $this->assertNotNull($frischeAuktion->hoechstgebotRelation);
        $this->assertSame($spaeteresGebotMitNiedrigererId->id, $frischeAuktion->hoechstgebotRelation->id);
        $this->assertSame($spaeteresGebotMitNiedrigererId->id, $frischeAuktion->hoechstgebot()?->id);
    }

    public function test_markdown_description_is_sanitized(): void
    {
        $auktion = Auktion::factory()->create([
            'beschreibung_markdown' => "**Sicher**\n\n![Verstecktes Bild](https://example.com/bild.png)\n\n[Unsicher](javascript:alert('xss'))\n\n[Dokumente](docs/auktion.md?ref=1#start)",
        ]);

        $htmlBeschreibung = $auktion->html_beschreibung;

        $this->assertStringContainsString('<strong>Sicher</strong>', $htmlBeschreibung);
        $this->assertStringNotContainsString('<img', $htmlBeschreibung);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $htmlBeschreibung);
        $this->assertStringContainsString('<a rel="noopener noreferrer">Unsicher</a>', $htmlBeschreibung);
        $this->assertStringContainsString('<a href="docs/auktion.md?ref=1#start" rel="noopener noreferrer">Dokumente</a>', $htmlBeschreibung);
    }

    public function test_html_beschreibung_is_cached_across_instances(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();

        $auktion = Auktion::factory()->create([
            'beschreibung_markdown' => '**Cached Inhalt**',
        ]);

        $first = $auktion->html_beschreibung;
        $cacheKey = sprintf('auktion:%s:html_beschreibung:%s:%s', $auktion->id, $auktion->updated_at->format('Uu'), md5('**Cached Inhalt**'));

        $this->assertTrue(Cache::has($cacheKey));
        $this->assertSame($first, Auktion::find($auktion->id)->html_beschreibung);
    }

    public function test_html_beschreibung_cache_key_changes_after_update(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();

        $auktion = Auktion::factory()->create([
            'beschreibung_markdown' => 'Erste Version',
        ]);

        $initialKey = sprintf('auktion:%s:html_beschreibung:%s:%s', $auktion->id, $auktion->updated_at->format('Uu'), md5('Erste Version'));

        $auktion->html_beschreibung;

        $this->assertTrue(Cache::has($initialKey));

        $auktion->update([
            'beschreibung_markdown' => 'Zweite Version',
        ]);
        $auktion->refresh();

        $updatedKey = sprintf('auktion:%s:html_beschreibung:%s:%s', $auktion->id, $auktion->updated_at->format('Uu'), md5('Zweite Version'));

        $this->assertNotSame($initialKey, $updatedKey);

        $auktion->html_beschreibung;

        $this->assertTrue(Cache::has($updatedKey));
    }

    #[TestWith([AuktionsStatus::Laufend, true])]
    #[TestWith([AuktionsStatus::ZumErsten, true])]
    #[TestWith([AuktionsStatus::ZumZweiten, true])]
    #[TestWith([AuktionsStatus::Verkauft, false])]
    #[TestWith([AuktionsStatus::NichtVerkauft, false])]
    public function test_kann_gebote_annehmen_depends_on_status(AuktionsStatus $status, bool $expected): void
    {
        $auktion = Auktion::factory()->create([
            'status' => $status,
        ]);

        $this->assertSame($expected, $auktion->kannGeboteAnnehmen());
    }
}
