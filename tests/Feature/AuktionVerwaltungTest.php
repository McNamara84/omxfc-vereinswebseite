<?php

namespace Tests\Feature;

use App\Enums\AuktionsStatus;
use App\Enums\Role;
use App\Models\Auktion;
use App\Models\AuktionGebot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class AuktionVerwaltungTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.testing_minimal_layout', true);
    }

    public function test_admin_can_open_auction_management_index(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $bieter = $this->createUserWithRole(Role::Mitglied);
        $auktion = Auktion::factory()->create();

        AuktionGebot::factory()->for($auktion)->for($bieter)->create([
            'bieter_name' => 'Index Bieter',
            'betrag_cent' => 2700,
        ]);

        $response = $this->withoutVite()->actingAs($admin)->get(route('admin.auktionen.index'));

        $response->assertOk();
        $response->assertSee('Auktionen verwalten');

        $geladeneAuktion = $response->viewData('auktionen')->firstWhere('id', $auktion->id);

        $this->assertNotNull($geladeneAuktion);
        $this->assertTrue($geladeneAuktion->relationLoaded('hoechstgebotRelation'));
        $this->assertFalse($geladeneAuktion->relationLoaded('gebote'));
        $this->assertSame('Index Bieter', $geladeneAuktion->hoechstgebot()?->bieter_name);
    }

    public function test_member_cannot_open_auction_management_index(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);

        $response = $this->actingAs($member)->get(route('admin.auktionen.index'));

        $response->assertForbidden();
    }

    public function test_member_can_open_auction_index(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);

        $response = $this->withoutVite()->actingAs($member)->get(route('auktionen.index'));

        $response->assertOk();
        $response->assertSee('Auktionen');
    }

    public function test_admin_can_create_new_auction(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $response = $this->actingAs($admin)->post(route('admin.auktionen.store'), [
            'titel' => 'Limitierte Sonderausgabe',
            'beschreibung_markdown' => '## Rares Sammlerstueck',
            'startbetrag' => '15.00',
            'mindestschritt' => '2.50',
        ]);

        $auktion = Auktion::query()->where('titel', 'Limitierte Sonderausgabe')->first();

        $response->assertRedirect(route('admin.auktionen.edit', $auktion));
        $this->assertNotNull($auktion);
        $this->assertSame(1500, $auktion->startbetrag_cent);
        $this->assertSame(250, $auktion->mindestschritt_cent);
        $this->assertSame(AuktionsStatus::Laufend, $auktion->status);
    }

    public function test_admin_cannot_create_auction_with_invalid_money_format(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $response = $this->from(route('admin.auktionen.create'))
            ->actingAs($admin)
            ->post(route('admin.auktionen.store'), [
                'titel' => 'Ungültige Werte',
                'beschreibung_markdown' => 'Test',
                'startbetrag' => '1e3',
                'mindestschritt' => '1.999',
            ]);

        $response->assertRedirect(route('admin.auktionen.create'));
        $response->assertSessionHasErrors(['startbetrag', 'mindestschritt']);
        $this->assertDatabaseMissing('auktionen', [
            'titel' => 'Ungültige Werte',
        ]);
    }

    public function test_admin_can_update_auction_before_first_bid(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $auktion = Auktion::factory()->create([
            'titel' => 'Vorher',
            'startbetrag_cent' => 1000,
            'mindestschritt_cent' => 100,
        ]);

        $this->actingAs($admin)->put(route('admin.auktionen.update', $auktion), [
            'titel' => 'Nachher',
            'beschreibung_markdown' => 'Aktualisiert',
            'startbetrag' => '12.00',
            'mindestschritt' => '1.50',
        ])->assertRedirect(route('admin.auktionen.edit', $auktion));

        $auktion->refresh();

        $this->assertSame('Nachher', $auktion->titel);
        $this->assertSame('Aktualisiert', $auktion->beschreibung_markdown);
        $this->assertSame(1200, $auktion->startbetrag_cent);
        $this->assertSame(150, $auktion->mindestschritt_cent);
    }

    public function test_auction_with_bids_rejects_changes_to_startbetrag_and_mindestschritt(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $bieter = $this->createUserWithRole(Role::Mitglied);
        $auktion = Auktion::factory()->create([
            'startbetrag_cent' => 1000,
            'mindestschritt_cent' => 100,
        ]);

        AuktionGebot::factory()->for($auktion)->for($bieter)->create([
            'bieter_name' => $bieter->name,
            'betrag_cent' => 1200,
        ]);

        $response = $this->from(route('admin.auktionen.edit', $auktion))
            ->actingAs($admin)
            ->put(route('admin.auktionen.update', $auktion), [
                'titel' => 'Geaenderter Titel',
                'beschreibung_markdown' => 'Neue Beschreibung',
                'startbetrag' => '30.00',
                'mindestschritt' => '5.00',
            ]);

        $response->assertRedirect(route('admin.auktionen.edit', $auktion));
        $response->assertSessionHasErrors(['startbetrag', 'mindestschritt']);

        $auktion->refresh();
        $this->assertSame(1000, $auktion->startbetrag_cent);
        $this->assertSame(100, $auktion->mindestschritt_cent);
    }

    public function test_auction_with_bids_still_allows_title_and_description_updates(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $bieter = $this->createUserWithRole(Role::Mitglied);
        $auktion = Auktion::factory()->create([
            'titel' => 'Alt',
            'beschreibung_markdown' => 'Alttext',
            'startbetrag_cent' => 1000,
            'mindestschritt_cent' => 100,
        ]);

        AuktionGebot::factory()->for($auktion)->for($bieter)->create([
            'bieter_name' => $bieter->name,
            'betrag_cent' => 1300,
        ]);

        $this->actingAs($admin)->put(route('admin.auktionen.update', $auktion), [
            'titel' => 'Neu',
            'beschreibung_markdown' => 'Neutext',
        ])->assertRedirect(route('admin.auktionen.edit', $auktion))
            ->assertSessionHasNoErrors();

        $auktion->refresh();
        $this->assertSame('Neu', $auktion->titel);
        $this->assertSame('Neutext', $auktion->beschreibung_markdown);
        $this->assertSame(1000, $auktion->startbetrag_cent);
        $this->assertSame(100, $auktion->mindestschritt_cent);
    }

    public function test_admin_can_delete_auction_without_bids(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $auktion = Auktion::factory()->create();

        $this->actingAs($admin)->delete(route('admin.auktionen.destroy', $auktion))
            ->assertRedirect(route('admin.auktionen.index'));

        $this->assertDatabaseMissing('auktionen', [
            'id' => $auktion->id,
        ]);
    }

    public function test_admin_cannot_delete_auction_with_bids(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $bieter = $this->createUserWithRole(Role::Mitglied);
        $auktion = Auktion::factory()->create();

        AuktionGebot::factory()->for($auktion)->for($bieter)->create([
            'bieter_name' => $bieter->name,
        ]);

        $this->actingAs($admin)->delete(route('admin.auktionen.destroy', $auktion))
            ->assertForbidden();
    }

    public function test_only_vorstand_can_progress_and_sell_auction_to_highest_bidder(): void
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $admin = $this->createUserWithRole(Role::Admin);
        $bieter = $this->createUserWithRole(Role::Mitglied);
        $auktion = Auktion::factory()->create();

        $gebot = AuktionGebot::factory()->for($auktion)->for($bieter)->create([
            'bieter_name' => 'Hoechstbieter',
            'betrag_cent' => 3200,
        ]);

        $this->actingAs($admin)->post(route('admin.auktionen.zum-ersten', $auktion))
            ->assertForbidden();

        $this->actingAs($vorstand)->post(route('admin.auktionen.zum-ersten', $auktion))
            ->assertRedirect();
        $this->assertSame(AuktionsStatus::ZumErsten, $auktion->fresh()->status);

        $this->actingAs($vorstand)->post(route('admin.auktionen.zum-zweiten', $auktion))
            ->assertRedirect();
        $this->assertSame(AuktionsStatus::ZumZweiten, $auktion->fresh()->status);

        $this->actingAs($vorstand)->post(route('admin.auktionen.verkaufen', $auktion))
            ->assertRedirect();

        $auktion->refresh();
        $this->assertSame(AuktionsStatus::Verkauft, $auktion->status);
        $this->assertSame($bieter->id, $auktion->verkauft_an_user_id);
        $this->assertSame($gebot->id, $auktion->verkauft_gebot_id);
        $this->assertNotNull($auktion->verkauft_at);
    }

    #[TestWith([Role::Admin])]
    #[TestWith([Role::Kassenwart])]
    public function test_non_vorstand_management_roles_cannot_advance_auction(Role $role): void
    {
        $manager = $this->createUserWithRole($role);
        $auktion = Auktion::factory()->create();

        $this->actingAs($manager)->post(route('admin.auktionen.zum-ersten', $auktion))
            ->assertForbidden();
    }

    public function test_vorstand_can_mark_auction_as_not_sold_after_zum_zweiten(): void
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $auktion = Auktion::factory()->create([
            'status' => AuktionsStatus::ZumZweiten,
        ]);

        $this->actingAs($vorstand)->post(route('admin.auktionen.nicht-verkauft', $auktion))
            ->assertRedirect();

        $auktion->refresh();
        $this->assertSame(AuktionsStatus::NichtVerkauft, $auktion->status);
        $this->assertNotNull($auktion->verkauft_at);
    }
}
