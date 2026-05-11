<?php

namespace Tests\Unit;

use App\Enums\AuktionsStatus;
use App\Enums\Role;
use App\Models\Auktion;
use App\Models\AuktionGebot;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_markdown_description_is_sanitized(): void
    {
        $auktion = Auktion::factory()->create([
            'beschreibung_markdown' => "**Sicher**\n\n<img src=x onerror=alert('xss')>",
        ]);

        $this->assertStringContainsString('<strong>Sicher</strong>', $auktion->html_beschreibung);
        $this->assertStringNotContainsString('<img', $auktion->html_beschreibung);
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
