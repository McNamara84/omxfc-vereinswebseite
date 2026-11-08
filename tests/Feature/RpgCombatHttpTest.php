<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\RpgCharacter;
use App\Models\RpgCombat;
use App\Models\RpgCombatMilestone;
use App\Services\RpgCombat\CombatQuery;
use App\Services\RpgCombat\CombatService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\RpgCombatFixtures;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class, RpgCombatFixtures::class);
dataset('RpgCombatHttpTest.localCreationDates', static fn (): array => ['winter after midnight' => ['2026-01-02T00:15:00+01:00', '02.01.2026'], 'summer after midnight' => ['2026-07-02T00:15:00+02:00', '02.07.2026']]);
beforeEach(function (): void {
    $this->combatFixtures();
});
test('invitation acceptance and pages do not change originals', function (): void {
    $input = $this->combatInput();
    $this->actingAs($this->player)->get(route('rpg.combats.create'))->assertOk()->assertSee('Startentfernung');
    $this->post(route('rpg.combats.store'), $input)->assertRedirect();
    $this->post(route('rpg.combats.store'), $input)->assertRedirect();
    $this->assertDatabaseCount('rpg_combats', 1);
    $combat = RpgCombat::firstOrFail();
    expect($combat->state)->toBeNull();
    $this->assertDatabaseCount('rpg_combat_character_locks', 0);
    $this->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('Arkon')->assertSee('Mira');
    $this->actingAs($this->opponent)->post(route('rpg.combats.command', $combat), ['command' => 'accept', 'submission_key' => (string) Str::uuid()])->assertRedirect();
    expect($combat->fresh()->status)->toBe('preparing');
    $this->assertDatabaseCount('rpg_combat_character_locks', 2);
    $this->getJson(route('rpg.combats.show', $combat))->assertOk()->assertJsonStructure(['revision', 'html']);
    $this->get(route('rpg.combats.index'))->assertOk()->assertSee('Arkon');
    expect($this->character->fresh()->payload)->toBe($this->progressionPayload())
        ->and($this->character->experienceBalance())->toBe(0)
        ->and(Activity::where('subject_type', RpgCombatMilestone::class)->count())->toBe(2);
});
test('native form binds each revision to the selected character', function (): void {
    $input = $this->combatInput();
    unset($input['revision'], $input['opponent_revision']);
    $input['revisions'] = [$this->character->id => 0, 99999 => 7];
    $input['opponent_revisions'] = [$this->otherCharacter->id => 0, 99999 => 8];
    $this->actingAs($this->player)->post(route('rpg.combats.store'), $input)->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseCount('rpg_combats', 1);
    expect(RpgCombat::firstOrFail()->participants()->orderBy('side')->pluck('character_revision')->all())->toBe([0, 0]);
});
test('native form rejects a missing selected revision', function (): void {
    $input = $this->combatInput();
    unset($input['revision']);
    $input['revisions'] = [99999 => 0];
    $this->actingAs($this->player)->post(route('rpg.combats.store'), $input)->assertSessionHasErrors('revision');
    $this->assertDatabaseCount('rpg_combats', 0);
});
test('native form does not replace a stale revision with the current one', function (): void {
    $input = $this->combatInput();
    unset($input['revision'], $input['opponent_revision']);
    $input['revisions'] = [$this->character->id => 0];
    $input['opponent_revisions'] = [$this->otherCharacter->id => 0, 99999 => 1];
    $this->otherCharacter->forceFill(['revision' => 1])->save();
    $this->actingAs($this->player)->post(route('rpg.combats.store'), $input)->assertSessionHasErrors();
    $this->assertDatabaseCount('rpg_combats', 0);
});
test('complete fight and retries never roll twice', function (): void {
    $combat = $this->activeCombat();
    $this->combatDice->values = [6, 6, 1, 1, 6];
    $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
    $input = ['kind' => 'attack', 'weapon' => 'faustschlag-tritt:1'];
    $url = route('rpg.combats.decide', [$combat, $decision->id]);
    $this->actingAs($this->player)->postJson($url, $input)->assertRedirect();
    $calls = $this->combatDice->calls;
    $this->postJson($url, $input)->assertRedirect();
    expect($this->combatDice->calls)->toBe($calls);
    $this->postJson($url, ['kind' => 'wait'])->assertConflict();
    $combat = $this->decision($combat, 'defense', 2, ['defense' => 'dodge']);
    $combat = $this->decision($combat, 'damage', 1);
    // Fist -1 plus critical +1 gives 6: severe, not lethal.
    expect($combat->state['actors'][2]['wounds'])->toBe([3]);
    $this->assertDatabaseCount('rpg_combat_character_locks', 2);
    $combat = app(CombatService::class)->command($this->opponent, $combat->id, 'surrender', (string) Str::uuid());
    expect($combat->status)->toBe('completed')
        ->and($combat->winner_side)->toBe(1);
    $this->assertDatabaseCount('rpg_combat_character_locks', 0);
    expect(RpgCombatMilestone::count())->toBe(3)
        ->and($combat->events()->orderBy('sequence')->pluck('sequence')->all())->toBe(range(1, $combat->events()->count()));
    $this->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('Kampfprotokoll');
});
test('characters saved through the editor can challenge accept and fight with zero strength', function (): void {
    $player = $this->progressionMember();
    $opponent = $this->progressionMember();
    $this->rpgTeam->users()->attach([$player->id, $opponent->id], ['role' => 'mitglied']);
    $attributes = ['st' => 0, 'ge' => 1, 'ro' => 1, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0];
    $input = ['figurenstaerke' => 2, 'player_name' => 'Spieler', 'gender' => 'maennlich', 'race' => 'Barbar', 'culture' => 'Landbewohner', 'barbar_attribute_bonus' => 'ge', 'attributes' => array_replace($attributes, ['st' => -1]), 'attribute_adjustments' => array_replace($attributes, ['st' => -1, 'ge' => 0]), 'skills' => [['name' => 'Nahkampf', 'value' => 3], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf: Viehzüchter', 'value' => 2], ['name' => 'Kunde: Wetter', 'value' => 1], ['name' => 'Athletik', 'value' => 3], ['name' => 'Fernkampf', 'value' => 3], ['name' => 'Handeln', 'value' => 3], ['name' => 'Fahren', 'value' => 3], ['name' => 'Feuerwaffen', 'value' => 1]], 'clothing' => 'kleidung-einfach', 'equipment_items' => [['id' => 'messer-dolch', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1], ['id' => 'wasserschlauch', 'quantity' => 1], ['id' => 'wochenration', 'quantity' => 1], ['id' => 'bogen', 'quantity' => 1]]];
    $this->actingAs($player)->post(route('rpg.characters.store'), $input + ['character_name' => 'Mäc'])->assertSessionHasNoErrors()->assertRedirect(route('rpg.characters.index'));
    $input['attributes'] = $attributes;
    $input['attribute_adjustments'] = array_replace($attributes, ['ge' => 0]);
    $this->actingAs($opponent)->post(route('rpg.characters.store'), $input + ['character_name' => 'Arthanasia'])->assertSessionHasNoErrors()->assertRedirect(route('rpg.characters.index'));
    $this->player = $player;
    $this->opponent = $opponent;
    $this->character = RpgCharacter::where('user_id', $player->id)->sole();
    $this->otherCharacter = RpgCharacter::where('user_id', $opponent->id)->sole();
    $originals = [];
    foreach ([$this->character, $this->otherCharacter] as $character) {
        $originals[$character->id] = $character->getRawOriginal();
        expect($character->payload['attributes'])->each->toBeString();
        foreach ($character->payload['skills'] as $skill) {
            expect($skill['value'])->toBeString();
        }
    }
    expect($this->character->payload['attributes']['st'])->toBe('-1')
        ->and($this->otherCharacter->payload['attributes']['st'])->toBe('0');
    $this->actingAs($player)->post(route('rpg.combats.store'), $this->combatInput())->assertSessionHasNoErrors()->assertRedirect();
    $combat = RpgCombat::sole();
    $this->actingAs($opponent)->post(route('rpg.combats.command', $combat), ['command' => 'accept', 'submission_key' => (string) Str::uuid()])->assertSessionHasNoErrors()->assertRedirect();
    $combat->refresh();
    expect($combat->status)->toBe('preparing');
    $this->assertDatabaseCount('rpg_combat_participants', 2);
    $this->assertDatabaseCount('rpg_combat_character_locks', 2);
    foreach ($combat->participants as $participant) {
        expect($participant->snapshot['attributes'])->each->toBeInt()
            ->and($participant->snapshot['skills'])->each->toBeInt();
    }
    foreach ([1, 2] as $side) {
        $combat = $this->decision($combat, 'prepare', $side, ['weapons' => [], 'shield' => false, 'skill' => 'Nahkampf']);
    }
    $this->combatDice->values = [1, 6];
    $combat = $this->decision($combat, 'initiative', 1);
    $combat = $this->decision($combat, 'initiative', 2);
    $this->combatDice->values = [6, 5, 2, 2, 1];
    $combat = $this->decision($combat, 'action', 2, ['kind' => 'attack', 'weapon' => 'faustschlag-tritt:1']);
    $combat = $this->decision($combat, 'defense', 1, ['defense' => 'dodge']);
    $combat = $this->decision($combat, 'damage', 2);
    $damage = $combat->events()->where('kind', 'roll')->get()->firstWhere('data.message', 'Schadenswurf');
    expect($damage)->not->toBeNull()
        ->and($damage->data['modifiers']['ST'])->toBe(0);
    $this->combatDice->values = [6, 5, 2, 2, 6];
    $combat = $this->decision($combat, 'action', 1, ['kind' => 'attack', 'weapon' => 'faustschlag-tritt:1', 'attribute' => 'st']);
    $combat = $this->decision($combat, 'defense', 2, ['defense' => 'dodge']);
    $combat = $this->decision($combat, 'damage', 1);
    $damage = $combat->events()->where('kind', 'roll')->orderByDesc('sequence')->get()->firstWhere('data.message', 'Schadenswurf');
    expect($damage)->not->toBeNull()
        ->and($damage->data['modifiers']['ST'])->toBe(-1)
        ->and($combat->state['actors'][1]['profile']['attributes']['st'])->toBe(-1)
        ->and($combat->state['actors'][2]['profile']['attributes']['st'])->toBe(0);
    foreach ([$this->character, $this->otherCharacter] as $character) {
        expect($character->fresh()->getRawOriginal())->toBe($originals[$character->id])
            ->and($character->experienceBalance())->toBe(0);
    }
});
test('combat listing shows the berlin creation date', function (string $instant, string $expectedDate): void {
    $this->travelTo(CarbonImmutable::parse($instant));
    $combat = $this->combat(false);
    expect($combat->fresh()->created_at->timezoneName)->toBe('UTC')
        ->and($combat->fresh()->created_at->format('d.m.Y'))->not->toBe($expectedDate);
    $this->actingAs($this->player)->get(route('rpg.combats.index'))->assertOk()->assertSee('Herausforderung offen · '.$expectedDate);
})->with('RpgCombatHttpTest.localCreationDates');
test('access is limited to participants and current leader', function (): void {
    $combat = $this->combat();
    $stranger = $this->progressionMember();
    $this->actingAs($stranger)->get(route('rpg.combats.show', $combat))->assertForbidden();
    $this->rpgTeam->users()->attach($stranger, ['role' => 'mitglied']);
    $this->getJson(route('rpg.combats.show', $combat))->assertForbidden();
    $this->actingAs($this->leader)->get(route('rpg.combats.show', $combat))->assertOk();
    $decision = $combat->decisions()->firstOrFail();
    $this->postJson(route('rpg.combats.decide', [$combat, $decision->id]), [])->assertForbidden();
    $this->actingAs($this->opponent)->postJson(route('rpg.combats.decide', [$combat, $decision->id]), [])->assertForbidden();
    $this->rpgTeam->users()->detach($this->player);
    $this->actingAs($this->player)->getJson(route('rpg.combats.show', $combat))->assertForbidden();
});
test('forged dice and foreign decision are rejected', function (): void {
    $combat = $this->activeCombat();
    $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
    $url = route('rpg.combats.decide', [$combat, $decision->id]);
    $this->actingAs($this->player)->postJson($url, ['kind' => 'attack', 'weapon' => 'faustschlag-tritt:1', 'dice' => [6, 6]])->assertUnprocessable();
    $this->postJson($url, ['kind' => 'attack', 'weapon' => ['bad']])->assertUnprocessable();
    $this->postJson($url, ['kind' => 'attack', 'weapon' => 'faustschlag-tritt:1', 'move' => 99999])->assertUnprocessable();
    expect($decision->fresh()->status)->toBe('pending')
        ->and($this->combatDice->calls)->toBe(2);
    $this->postJson(route('rpg.combats.decide', [$combat, 99999]), [])->assertNotFound();
});
test('stale character revision requires new invitation', function (): void {
    $combat = $this->combat(false);
    $this->character->increment('revision');
    $this->actingAs($this->opponent)->postJson(route('rpg.combats.command', $combat), ['command' => 'accept', 'submission_key' => (string) Str::uuid()])->assertUnprocessable();
    $this->assertDatabaseCount('rpg_combat_character_locks', 0);
});
test('private dashboard tasks follow ownership and current leadership', function (): void {
    $query = app(CombatQuery::class);
    $combat = $this->activeCombat();
    expect($query->hints($this->player)[0]['tasks'][0]['label'])->toBe('Handlung wählen')
        ->and($query->hints($this->player)[0]['tasks'][0]['due_at'])->not->toBeNull()
        ->and($query->hints($this->opponent)[0]['tasks'])->toBe([])
        ->and($query->hints($this->leader))->toBe([]);
    $combat = $this->decision($combat, 'action', 1, ['kind' => 'creative', 'description' => 'Staub aufwirbeln']);
    expect($query->hints($this->leader)[0]['tasks'][0]['label'])->toBe('Regelfrage entscheiden');
    $member = $this->progressionMember();
    $this->rpgTeam->users()->attach($member, ['role' => 'mitglied']);
    expect($query->hints($member))->toBe([]);
    $this->rpgTeam->update(['user_id' => $member->id]);
    expect($query->hints($this->leader))->toBe([])
        ->and($query->hints($member)[0]['tasks'][0]['label'])->toBe('Regelfrage entscheiden');
    $this->actingAs($member)->get('/dashboard')->assertOk()->assertSee('Regelfrage entscheiden')->assertSee('bis ');
    $this->rpgTeam->update(['user_id' => $this->player->id]);
    expect(array_column($query->hints($this->player)[0]['tasks'], 'label'))->not->toContain('Regelfrage entscheiden');
});
test('invitation dashboard displays acceptance deadline', function (): void {
    $combat = $this->combat(false);
    $hint = app(CombatQuery::class)->hints($this->opponent)[0];
    expect($hint['tasks'][0]['label'])->toBe('Herausforderung annehmen oder ablehnen')
        ->and($combat->expires_at->equalTo($hint['tasks'][0]['due_at']))->toBeTrue();
    $this->actingAs($this->opponent)->get('/dashboard')->assertOk()->assertSee('Herausforderung annehmen oder ablehnen');
});
test('unrepresented rule case can be neutrally ended by uninvolved leader', function (): void {
    $combat = $this->activeCombat();
    $combat = $this->decision($combat, 'action', 1, ['kind' => 'creative', 'description' => 'Ein erzählerischer Sonderfall']);
    $decision = $combat->decisions()->where('type', 'ruling')->where('status', 'pending')->sole();
    $url = route('rpg.combats.decide', [$combat, $decision->id]);
    $this->actingAs($this->leader)->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('Ein erzählerischer Sonderfall')->assertSee('Mit Begründung neutral abbrechen');
    $this->actingAs($this->player)->postJson($url, ['abort' => true, 'reason' => 'Spielerentscheidung'])->assertForbidden();
    $this->actingAs($this->leader)->postJson($url, ['abort' => true, 'reason' => ''])->assertUnprocessable();
    $this->post($url, ['abort' => '1', 'choices' => ['context' => 'none'], 'reason' => 'Dieser Fall lässt sich nicht ausreichend abbilden.'])->assertRedirect();
    $combat->refresh();
    expect($combat->result)->toBe('judicial_abort')
        ->and($combat->winner_side)->toBeNull()
        ->and($combat->state['continuation'])->toBeNull();
    $this->assertDatabaseCount('rpg_combat_character_locks', 0);
    expect($combat->decisions()->whereIn('status', ['pending', 'paused'])->count())->toBe(0);
    $this->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('Durch die AG-Leitung neutral abgebrochen');
    $this->post($url, ['abort' => '1', 'choices' => ['context' => 'none'], 'reason' => 'Dieser Fall lässt sich nicht ausreichend abbilden.'])->assertRedirect();
    expect(RpgCombatMilestone::count())->toBe(3);
});
