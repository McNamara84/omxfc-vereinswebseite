<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\DashboardActivityFeed;
use App\Models\Activity;
use App\Models\RpgAdvancementRequest;
use App\Services\Dashboard\DashboardActivityQuery;
use App\Services\RpgCharacterAdvancementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\RpgProgressionFixtures;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class, RpgProgressionFixtures::class);

beforeEach(function (): void {
    $this->progressionFixtures();
});

test('award picker and requests use the same active ag membership rule', function (): void {
    $this->rpgTeam->users()->updateExistingPivot($this->player->id, ['role' => Role::Anwaerter->value]);
    $this->actingAs($this->leader)->get(route('rpg.adventures.create'))->assertOk()->assertViewHas('config', fn ($config) => ! $config['characters']->pluck('id')->contains($this->character->id));
    $this->postJson(route('rpg.adventures.preview'), $this->awardInput())->assertUnprocessable();
    $this->postJson(route('rpg.adventures.store'), $this->awardInput())->assertUnprocessable();
    $this->assertDatabaseCount('rpg_adventures', 0);
    $this->rpgTeam->users()->updateExistingPivot($this->player->id, ['role' => Role::Mitglied->value]);
    $this->get(route('rpg.adventures.create'))->assertOk()->assertViewHas('config', fn ($config) => $config['characters']->pluck('id')->contains($this->character->id));
    $this->postJson(route('rpg.adventures.preview'), $this->awardInput())->assertOk();
});

test('full http award preview request and approval flow', function (): void {
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
});

test('feed is public but details and button are scoped', function (): void {
    $this->credit();
    $other = $this->progressionMember();
    Livewire::actingAs($other)->test(DashboardActivityFeed::class)->assertSee('Charakter Arkon')->assertSee('60 EP')->assertDontSee('vertrauliche Bewertung')->assertDontSee('Charakter verbessern')->call('selectFilter', 'club')->assertSee('Charakter Arkon');
    Livewire::actingAs($this->player)->test(DashboardActivityFeed::class)->assertSee('Charakter verbessern');
    $this->actingAs($other)->get(route('rpg.characters.history', $this->character))->assertForbidden();
    $this->get(route('rpg.characters.pdf', $this->character))->assertForbidden();
    $query = app(DashboardActivityQuery::class);
    $activities = $query->findMany(Activity::where('action', Activity::ACTION_RPG_EXPERIENCE_AWARDED)->pluck('id')->all());
    $attributes = $activities->first()->subject->getAttributes();
    expect($attributes)->not->toHaveKey('criteria')->not->toHaveKey('reason')
        ->and($activities->first()->subject->character->getAttributes())->not->toHaveKey('payload');
});

test('tampered price status and character payload are not applied', function (): void {
    $this->credit();
    $this->actingAs($this->player)->postJson(route('rpg.characters.advancements.store', $this->character), $this->advancementInput() + ['cost' => 0, 'status' => 'approved', 'payload' => ['attributes' => ['st' => 999]]])->assertOk();
    $request = RpgAdvancementRequest::firstOrFail();
    expect($request->status)->toBe('pending')
        ->and($request->cost)->toBe(6)
        ->and($this->character->fresh()->payload['attributes']['st'])->toBe(0);
    $this->post(route('rpg.advancements.approve', $request))->assertForbidden();
});

test('reject and withdraw endpoints and foreign request access', function (): void {
    $this->credit();
    $service = app(RpgCharacterAdvancementService::class);
    $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
    $other = $this->progressionMember();
    $this->rpgTeam->users()->attach($other, ['role' => 'Mitglied']);
    $this->actingAs($other)->get(route('rpg.advancements.show', $request))->assertForbidden();
    $this->post(route('rpg.advancements.withdraw', $request))->assertForbidden();
    $this->actingAs($this->leader)->post(route('rpg.advancements.reject', $request), ['reason' => 'Bitte begründen'])->assertRedirect();
    expect($request->fresh()->status)->toBe('rejected');
    $next = $service->submit($this->player, $this->character->id, $this->advancementInput());
    $this->actingAs($this->player)->post(route('rpg.advancements.withdraw', $next))->assertRedirect();
    expect($next->fresh()->status)->toBe('withdrawn');
});

test('deleted character has safe feed fallback and no remaining ledger', function (): void {
    $this->credit();
    $this->actingAs($this->player)->delete(route('rpg.characters.destroy', $this->character))->assertRedirect();
    $this->assertDatabaseCount('rpg_experience_entries', 0);
    $this->assertDatabaseCount('rpg_experience_awards', 0);
    Livewire::actingAs($this->player)->test(DashboardActivityFeed::class)->assertSee('Gelöschter Eintrag')->assertDontSee('Charakter verbessern');
});

test('leader can document missing legacy origins without changing values or points', function (): void {
    $payload = $this->character->payload;
    $payload['character']['race'] = 'Barbar';
    $payload['advantages'] = ['Gesteigertes Attribut'];
    $this->character->update(['payload' => $payload]);
    $this->actingAs($this->leader)->get(route('rpg.characters.history', $this->character))->assertOk()->assertSee('Fehlende Herkunft');
    $this->post(route('rpg.characters.clarify', $this->character), ['revision' => 0, 'reason' => 'Alter Charakterbogen vom Spielabend.', 'barbar_attribute' => 'ge', 'targets' => [0 => 'st']])->assertRedirect();
    $current = $this->character->fresh();
    expect($current->payload['attributes'])->toBe($payload['attributes'])
        ->and($current->payload['progression']['race_modifiers'])->toBe(['ge' => 1])
        ->and($current->payload['advantage_effects'][0]['target'])->toBe('st')
        ->and($current->revision)->toBe(1)
        ->and($current->experienceBalance())->toBe(0);
});
