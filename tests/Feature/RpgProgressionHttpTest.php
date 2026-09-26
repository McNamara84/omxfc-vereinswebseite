<?php

namespace Tests\Feature;

use App\Livewire\DashboardActivityFeed;
use App\Models\Activity;
use App\Models\RpgAdvancementRequest;
use App\Services\Dashboard\DashboardActivityQuery;
use App\Services\RpgCharacterAdvancementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\RpgProgressionFixtures;
use Tests\TestCase;

class RpgProgressionHttpTest extends TestCase
{
    use RefreshDatabase, RpgProgressionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->progressionFixtures();
    }

    public function test_full_http_award_preview_request_and_approval_flow(): void
    {
        $award = $this->awardInput();
        $this->actingAs($this->leader)->get(route('rpg.adventures.create'))->assertOk()->assertSee('Erfahrungspunkte vergeben');
        $this->postJson(route('rpg.adventures.preview'), $award)->assertOk()->assertJsonPath('awards.0.calculated_points', 4);
        $this->assertDatabaseCount('rpg_adventures', 0);
        $url = $this->postJson(route('rpg.adventures.store'), $award)->assertOk()->json('redirect');
        $this->get($url)->assertOk()->assertSee('60 EP');
        $this->get(route('rpg.adventures.index'))->assertOk()->assertSee('Die Ruinen');
        $this->actingAs($this->player)->get(route('rpg.characters.index'))->assertOk()->assertSee('60 EP')->assertSee('Charakter verbessern');
        $this->get(route('rpg.characters.improve', $this->character))->assertOk();
        $input = $this->advancementInput();
        $this->postJson(route('rpg.characters.advancement-preview', $this->character), $input)->assertOk()->assertJsonPath('cost', 6)->assertJsonPath('remaining', 54);
        $url = $this->postJson(route('rpg.characters.advancements.store', $this->character), $input)->assertOk()->json('redirect');
        $this->get($url)->assertOk()->assertSee('Zur Prüfung');
        $this->get(route('rpg.characters.improve', $this->character))->assertOk()->assertSee('Ein Antrag liegt bereits zur Prüfung vor');
        $request = RpgAdvancementRequest::firstOrFail();
        $this->actingAs($this->leader)->get(route('rpg.advancements.index'))->assertOk()->assertSee('Arkon');
        $this->post(route('rpg.advancements.approve', $request))->assertRedirect();
        $this->get($url)->assertOk()->assertSee('Genehmigt');
        $this->actingAs($this->player)->get(route('rpg.characters.history', $this->character))->assertOk()->assertSee('54 EP')->assertSee('vertrauliche Bewertung');
        $this->get(route('rpg.characters.pdf', $this->character))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_feed_is_public_but_details_and_button_are_scoped(): void
    {
        $this->credit();
        $other = $this->progressionMember();
        Livewire::actingAs($other)->test(DashboardActivityFeed::class)
            ->assertSee('Charakter Arkon')->assertSee('60 EP')->assertDontSee('vertrauliche Bewertung')
            ->assertDontSee('Charakter verbessern')->call('selectFilter', 'club')->assertSee('Charakter Arkon');
        Livewire::actingAs($this->player)->test(DashboardActivityFeed::class)->assertSee('Charakter verbessern');
        $this->actingAs($other)->get(route('rpg.characters.history', $this->character))->assertForbidden();
        $this->get(route('rpg.characters.pdf', $this->character))->assertForbidden();
        $query = app(DashboardActivityQuery::class);
        $activities = $query->findMany(Activity::where('action', Activity::ACTION_RPG_EXPERIENCE_AWARDED)->pluck('id')->all());
        $attributes = $activities->first()->subject->getAttributes();
        $this->assertArrayNotHasKey('criteria', $attributes);
        $this->assertArrayNotHasKey('reason', $attributes);
        $this->assertArrayNotHasKey('payload', $activities->first()->subject->character->getAttributes());
    }

    public function test_tampered_price_status_and_character_payload_are_not_applied(): void
    {
        $this->credit();
        $this->actingAs($this->player)->postJson(route('rpg.characters.advancements.store', $this->character), $this->advancementInput() + [
            'cost' => 0, 'status' => 'approved', 'payload' => ['attributes' => ['st' => 999]],
        ])->assertOk();
        $request = RpgAdvancementRequest::firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertSame(6, $request->cost);
        $this->assertSame(0, $this->character->fresh()->payload['attributes']['st']);
        $this->post(route('rpg.advancements.approve', $request))->assertForbidden();
    }

    public function test_reject_and_withdraw_endpoints_and_foreign_request_access(): void
    {
        $this->credit();
        $service = app(RpgCharacterAdvancementService::class);
        $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
        $other = $this->progressionMember();
        $this->rpgTeam->users()->attach($other, ['role' => 'Mitglied']);
        $this->actingAs($other)->get(route('rpg.advancements.show', $request))->assertForbidden();
        $this->post(route('rpg.advancements.withdraw', $request))->assertForbidden();
        $this->actingAs($this->leader)->post(route('rpg.advancements.reject', $request), ['reason' => 'Bitte begründen'])->assertRedirect();
        $this->assertSame('rejected', $request->fresh()->status);
        $next = $service->submit($this->player, $this->character->id, $this->advancementInput());
        $this->actingAs($this->player)->post(route('rpg.advancements.withdraw', $next))->assertRedirect();
        $this->assertSame('withdrawn', $next->fresh()->status);
    }

    public function test_deleted_character_has_safe_feed_fallback_and_no_remaining_ledger(): void
    {
        $this->credit();
        $this->actingAs($this->player)->delete(route('rpg.characters.destroy', $this->character))->assertRedirect();
        $this->assertDatabaseCount('rpg_experience_entries', 0);
        $this->assertDatabaseCount('rpg_experience_awards', 0);
        Livewire::actingAs($this->player)->test(DashboardActivityFeed::class)->assertSee('Gelöschter Eintrag')->assertDontSee('Charakter verbessern');
    }

    public function test_leader_can_document_missing_legacy_origins_without_changing_values_or_points(): void
    {
        $payload = $this->character->payload;
        $payload['character']['race'] = 'Barbar';
        $payload['advantages'] = ['Gesteigertes Attribut'];
        $this->character->update(['payload' => $payload]);
        $this->actingAs($this->leader)->get(route('rpg.characters.history', $this->character))->assertOk()->assertSee('Fehlende Herkunft');
        $this->post(route('rpg.characters.clarify', $this->character), [
            'revision' => 0, 'reason' => 'Alter Charakterbogen vom Spielabend.', 'barbar_attribute' => 'ge', 'targets' => [0 => 'st'],
        ])->assertRedirect();
        $current = $this->character->fresh();
        $this->assertSame($payload['attributes'], $current->payload['attributes']);
        $this->assertSame(['ge' => 1], $current->payload['progression']['race_modifiers']);
        $this->assertSame('st', $current->payload['advantage_effects'][0]['target']);
        $this->assertSame(1, $current->revision);
        $this->assertSame(0, $current->experienceBalance());
    }
}
