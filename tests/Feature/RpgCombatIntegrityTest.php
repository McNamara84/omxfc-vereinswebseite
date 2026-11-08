<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\DashboardActivityFeed;
use App\Models\RpgCharacter;
use App\Models\RpgCombat;
use App\Services\RpgCombat\CombatService;
use App\Services\RpgCombat\CombatSnapshotFactory;
use App\Services\RpgCombat\CombatTraits;
use App\Support\RpgCharEditorRuleCatalog;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgCombatIntegrityTestFixtures::class);
dataset('RpgCombatIntegrityTest.invalidPayloads', static function (): array {
    $payloads = [['attributes' => ['st' => 'six']], ['skills' => [['name' => 'Nahkampf', 'value' => -2]]], ['advantages' => [['wrong']]], ['advantage_counts' => ['Panzerung' => 100000]], ['advantages' => ['Unbekannt']], ['equipment' => ['items' => [['id' => 'missing', 'quantity' => 1]]]], ['equipment' => ['items' => [['id' => 'schwert', 'quantity' => 0]]]], ['equipment' => ['active_armor_id' => 'lederruestung']], ['advantages' => ['Psychische Kraft'], 'advantage_effects' => [['name' => 'Psychische Kraft', 'target' => '']]], ['rule_sources' => [['id' => 'invented']]], ['advantage_effects' => [['name' => 'Psychische Kraft', 'target' => 'Pyrokinese']]], ['character' => ['race' => 'Marsianer']], ['attributes' => null], ['attributes' => '0'], ['attributes' => ['st' => null]], ['attributes' => ['st' => false]], ['attributes' => ['st' => '1.5']], ['attributes' => ['st' => '1e1']], ['attributes' => ['st' => str_repeat('9', 100)]], ['attributes' => ['st' => '21']], ['attributes' => ['st' => '-21']], ['skills' => null], ['skills' => [['name' => 'Nahkampf', 'value' => '101']]], ['skills' => [['name' => 'Nahkampf', 'value' => null]]], ['skills' => [['name' => 'Nahkampf', 'value' => false]]], ['skills' => [['name' => 'Nahkampf', 'value' => '1.5']]]];
    $cases = [];
    foreach ($payloads as $index => $payload) {
        foreach ([false, true] as $opponent) {
            $cases[($opponent ? 'opponent' : 'challenger').' '.$index] = [$payload, $opponent];
        }
    }

    return $cases;
});
dataset('RpgCombatIntegrityTest.missingNumericValues', static function (): iterable {
    foreach ([false, true] as $opponent) {
        foreach (['attributes', 'skills'] as $field) {
            yield ($opponent ? 'opponent' : 'challenger').' '.$field => [$opponent, $field];
        }
    }
});
beforeEach(function (): void {
    $this->combatFixtures();
});
test('reverse duplicate and self challenges are rejected', function (): void {
    $combat = $this->combat(false);
    $this->actingAs($this->opponent)->postJson(route('rpg.combats.store'), $this->combatInput(['character_id' => $this->otherCharacter->id, 'opponent_id' => $this->character->id]))->assertUnprocessable();
    $owned = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $this->progressionPayload()]);
    $this->actingAs($this->player)->postJson(route('rpg.combats.store'), $this->combatInput(['opponent_id' => $owned->id]))->assertForbidden();
    expect(RpgCombat::count())->toBe(1);
});
test('active character cannot accept a second fight', function (): void {
    $first = $this->combat(false);
    $owned = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $this->progressionPayload()])->refresh();
    $second = app(CombatService::class)->challenge($this->player, $this->combatInput(['character_id' => $owned->id]));
    app(CombatService::class)->command($this->opponent, $first->id, 'accept', (string) Str::uuid());
    $this->actingAs($this->opponent)->postJson(route('rpg.combats.command', $second), ['command' => 'accept', 'submission_key' => (string) Str::uuid()])->assertUnprocessable();
    $this->assertDatabaseCount('rpg_combat_character_locks', 2);
    expect($second->fresh()->accepted_at)->toBeNull();
});
test('edits after acceptance do not change fighting snapshot', function (): void {
    $combat = $this->activeCombat();
    $before = $combat->state['actors'][1]['profile'];
    $payload = $this->character->payload;
    $payload['attributes']['st'] = 9;
    $this->character->update(['payload' => $payload]);
    $this->character->increment('revision');
    $combat = $this->decision($combat, 'action', 1, ['kind' => 'wait']);
    expect($combat->state['actors'][1]['profile'])->toBe($before)
        ->and($this->character->fresh()->payload['attributes']['st'])->toBe(9);
});
test('abort requires both players and never pauses decisions', function (): void {
    $combat = $this->activeCombat();
    $service = app(CombatService::class);
    $combat = $service->command($this->player, $combat->id, 'abort', (string) Str::uuid());
    expect($combat->isOpen())->toBeTrue()
        ->and($combat->decisions()->where('status', 'pending')->count())->toBe(1);
    $combat = $service->command($this->opponent, $combat->id, 'abort', (string) Str::uuid());
    expect($combat->result)->toBe('cancelled');
    $this->assertDatabaseCount('rpg_combat_character_locks', 0);
});
test('sealed declaration is not in html or polling json', function (): void {
    $combat = $this->combat();
    foreach ([1, 2] as $side) {
        $combat = $this->decision($combat, 'prepare', $side, ['weapons' => [], 'shield' => false, 'skill' => 'Nahkampf']);
    }
    foreach ([1, 2] as $side) {
        $combat = $this->decision($combat, 'initiative', $side);
    }
    $combat = $this->decision($combat, 'ruling', 0, ['choices' => ['initiative_tie' => 'simultaneous'], 'reason' => 'Gleiche Initiative bleibt erhalten.']);
    $combat = $this->decision($combat, 'action', 1, ['kind' => 'creative', 'description' => 'GEHEIME-ANSAGE-8472']);
    $this->actingAs($this->opponent)->getJson(route('rpg.combats.show', $combat))->assertOk()->assertDontSee('GEHEIME-ANSAGE-8472');
    $this->actingAs($this->leader)->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('GEHEIME-ANSAGE-8472');
    $combat = $this->decision($combat, 'ruling', 0, ['choices' => ['context' => 'none'], 'reason' => 'Keine zusätzliche Wirkung.']);
    $this->actingAs($this->opponent)->get(route('rpg.combats.show', $combat))->assertOk()->assertDontSee('GEHEIME-ANSAGE-8472');
    $combat = $this->decision($combat, 'action', 2, ['kind' => 'wait']);
    $this->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('GEHEIME-ANSAGE-8472');
});
test('current leader only can rule and participating leader cannot', function (): void {
    $combat = $this->activeCombat();
    $combat = $this->decision($combat, 'action', 1, ['kind' => 'creative', 'description' => 'Sand aufwirbeln.']);
    $decision = $combat->decisions()->where('type', 'ruling')->where('status', 'pending')->firstOrFail();
    $url = route('rpg.combats.decide', [$combat, $decision->id]);
    $this->rpgTeam->update(['user_id' => $this->player->id]);
    $this->actingAs($this->leader)->postJson($url, ['choices' => ['context' => 'none'], 'reason' => 'Auslegung'])->assertForbidden();
    $this->actingAs($this->player)->postJson($url, ['choices' => ['context' => 'plus_two'], 'reason' => 'Selbstentscheidung'])->assertForbidden();
    $this->travelTo($decision->due_at);
    app(CombatService::class)->decide(null, $combat->id, $decision->id);
    expect($decision->fresh()->automatic)->toBeTrue();
});
test('snapshot omits biography and accounts for all traits', function (): void {
    $payload = $this->progressionPayload();
    $payload['character']['biography'] = 'PRIVATE-HISTORY';
    $payload['advantages'] = ['Kampfreflexe', 'Zäh', 'Gesteigertes Attribut'];
    $payload['equipment']['items'] = [['id' => 'revolver', 'quantity' => 2], ['id' => 'energiegewehr', 'quantity' => 1]];
    $this->character->update(['payload' => $payload]);
    $snapshot = app(CombatSnapshotFactory::class)->make($this->character);
    expect(json_encode($snapshot))->not->toContain('PRIVATE-HISTORY')
        ->and($snapshot['weapons']['revolver:1']['loaded'])->toBe(6)
        ->and($snapshot['weapons']['revolver:2']['reserve'])->toBe(18)
        ->and($snapshot['weapons']['energiegewehr:1']['loaded'])->toBe(100)
        ->and($snapshot['weapons']['energiegewehr:1']['reserve'])->toBe(0)
        ->and(CombatTraits::describe($snapshot))->toHaveCount(4);
});
test('invalid saved character is rejected without partial invitation', function (array $changes, bool $opponent): void {
    $payload = array_replace_recursive($this->progressionPayload(), $changes);
    $character = $opponent ? $this->otherCharacter : $this->character;
    $character->update(['payload' => $payload]);
    $this->actingAs($this->player)->postJson(route('rpg.combats.store'), $this->combatInput())->assertUnprocessable()->assertJsonValidationErrors('character');
    $this->assertChallengeRolledBack();
    expect($character->fresh()->payload)->toBe($payload);
})->with('RpgCombatIntegrityTest.invalidPayloads');
test('missing numeric values are not defaulted when challenging', function (bool $opponent, string $field): void {
    $character = $opponent ? $this->otherCharacter : $this->character;
    $payload = $character->payload;
    if ($field === 'attributes') {
        unset($payload['attributes']['st']);
    } else {
        unset($payload['skills'][0]['value']);
    }
    $character->update(['payload' => $payload]);
    $this->actingAs($this->player)->postJson(route('rpg.combats.store'), $this->combatInput())->assertUnprocessable()->assertJsonValidationErrors('character');
    $this->assertChallengeRolledBack();
})->with('RpgCombatIntegrityTest.missingNumericValues');
test('public dashboard shows only milestones and escapes names', function (): void {
    $this->character->update(['character_name' => '<script>attack()</script>']);
    $combat = $this->combat();
    $outsider = $this->progressionMember();
    Livewire::actingAs($outsider)->test(DashboardActivityFeed::class)->set('filter', 'club')->assertSee('zum Übungskampf herausgefordert')->assertSee('beginnen ihren Übungskampf')->assertDontSeeHtml('<script>attack()</script>')->assertDontSee('snapshot_hash')->assertDontSee('Kampfprotokoll');
    $this->actingAs($this->player)->get(route('dashboard'))->assertOk()->assertSee('Persönliche Übungskämpfe');
    $combat = app(CombatService::class)->command($this->player, $combat->id, 'surrender', (string) Str::uuid());
    Livewire::actingAs($outsider)->test(DashboardActivityFeed::class)->assertSee('Mira hat gewonnen.');
});
test('enabled expansion uses saved values and never applies race bonuses twice', function (): void {
    $payload = $this->progressionPayload();
    $payload['character']['race'] = 'Marsianer';
    $payload['rule_sources'] = RpgCharEditorRuleCatalog::snapshots(['expansion-1' => true]);
    $payload['attributes']['wa'] = 2;
    $this->character->update(['payload' => $payload]);
    $combat = $this->combat();
    expect($combat->state['actors'][1]['profile']['attributes']['wa'])->toBe(2)
        ->and(array_column($combat->participants[0]->snapshot['sources'], 'id'))->toContain('expansion-1');
});
test('participant snapshot is immutable and original links remain correct', function (): void {
    $combat = $this->combat();
    $participant = $combat->participants[0];
    expect($participant->combat->id)->toBe($combat->id)
        ->and($combat->decisions()->first()->combat->id)->toBe($combat->id)
        ->and(fn () => $participant->update(['snapshot' => ['forged' => true]]))->toThrow(\LogicException::class);
});
test('team deletion cancels fights and decline withdraw are idempotent', function (): void {
    $service = app(CombatService::class);
    $combat = $this->combat(false);
    $key = (string) Str::uuid();
    $service->command($this->opponent, $combat->id, 'decline', $key);
    $service->command($this->opponent, $combat->id, 'decline', $key);
    expect($combat->fresh()->status)->toBe('declined');
    $combat = $this->combat(false);
    $service->command($this->player, $combat->id, 'withdraw', (string) Str::uuid());
    expect($combat->fresh()->status)->toBe('withdrawn');
    $combat = $this->combat();
    $this->rpgTeam->delete();
    expect($combat->fresh()->result)->toBe('membership_changed');
    $this->assertDatabaseCount('rpg_combat_character_locks', 0);
});
