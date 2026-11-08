<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Activity;
use App\Models\RpgCharacter;
use App\Models\RpgCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\RpgCheckFixtures;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class, RpgCheckFixtures::class);

beforeEach(function (): void {
    $this->checkFixtures();
});

test('preview create roll and history with server values', function (): void {
    $input = $this->checkInput();
    $this->actingAs($this->leader)->get(route('rpg.checks.create'))->assertOk()->assertSee('noch nicht verfügbar');
    $this->postJson(route('rpg.checks.preview'), $input)->assertOk()->assertJsonPath('participants.0.base_total', 2);
    $this->assertDatabaseCount('rpg_checks', 0);
    $this->postJson(route('rpg.checks.store'), $input)->assertOk();
    $this->postJson(route('rpg.checks.store'), $input)->assertOk();
    $this->assertDatabaseCount('rpg_checks', 1);
    $check = RpgCheck::firstOrFail();
    $this->dice([4, 4]);
    $this->actingAs($this->player)->get(route('rpg.checks.index'))->assertOk()->assertSee('Proben');
    $response = $this->get(route('rpg.checks.show', $check))->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->toContain('private');
    $this->getJson(route('rpg.checks.hints'))->assertJsonPath('total', 1);
    $url = route('rpg.checks.roll', [$check, $check->participants->first()]);
    $this->postJson($url)->assertOk()->assertJsonPath('participants.0.total', 10)->assertJsonPath('participants.0.result_kind', 'success')->assertJsonPath('participants.0.margin', 0);
    $this->postJson($url)->assertOk()->assertJsonPath('participants.0.dice', [4, 4]);
    $this->getJson(route('rpg.checks.hints'))->assertJsonPath('total', 0);
    $this->getJson(route('rpg.checks.index', ['tab' => 'history']))->assertJsonPath('total', 1);
    $this->actingAs($this->leader)->getJson(route('rpg.checks.hints'))->assertJsonPath('recent.0.id', $check->id);
    expect($this->character->experienceBalance())->toBe(0)
        ->and($this->character->fresh()->payload)->toBe($this->progressionPayload())
        ->and(Activity::where('subject_type', RpgCheck::class)->count())->toBe(0);
});

test('only current members and leader have access', function (): void {
    $admin = $this->progressionMember(Role::Admin);
    $check = $this->createCheck();
    $this->actingAs($admin)->get(route('rpg.checks.index'))->assertForbidden();
    $this->postJson(route('rpg.checks.store'), $this->checkInput())->assertForbidden();
    $this->actingAs($this->player)->get(route('rpg.checks.create'))->assertForbidden();
    $this->postJson(route('rpg.checks.store'), $this->checkInput())->assertForbidden();
    $this->actingAs($this->otherPlayer)->getJson(route('rpg.checks.show', $check))->assertNotFound();
    $url = route('rpg.checks.roll', [$check, $check->participants->first()]);
    $this->postJson($url)->assertForbidden();
    $this->actingAs($this->leader)->postJson($url)->assertForbidden();
    $this->rpgTeam->users()->detach($this->player);
    $this->actingAs($this->player)->getJson(route('rpg.checks.hints'))->assertForbidden();
    $this->postJson($url)->assertForbidden();
});

test('group creation is atomic and each result is private', function (): void {
    $input = $this->checkInput('opposed');
    $input['mode'] = 'fixed';
    $input['difficulty'] = 9;
    $this->actingAs($this->leader)->postJson(route('rpg.checks.store'), $input)->assertOk()->assertJsonCount(2, 'check_ids');
    $this->actingAs($this->player)->getJson(route('rpg.checks.index'))->assertJsonPath('total', 1);
    $this->actingAs($this->otherPlayer)->getJson(route('rpg.checks.index'))->assertJsonPath('total', 1);
    $input['submission_key'] = (string) Str::uuid();
    $this->rpgTeam->users()->detach($this->otherPlayer);
    $this->actingAs($this->leader)->postJson(route('rpg.checks.store'), $input)->assertUnprocessable();
    $this->assertDatabaseCount('rpg_check_batches', 1);
    $this->assertDatabaseCount('rpg_checks', 2);
});

test('invalid inputs and server controlled fields are rejected', function (): void {
    $this->actingAs($this->leader);
    $input = $this->checkInput();
    foreach ([array_replace($input, ['mode' => 'npc']), $input + ['total' => 999], array_replace($input, ['visibility' => 'public']), array_replace($input, ['difficulty' => 1.5]), array_replace($input, ['description' => '   ']), array_replace($input, ['mode' => 'opposed', 'difficulty' => null])] as $invalid) {
        $this->postJson(route('rpg.checks.store'), $invalid)->assertUnprocessable();
    }
    foreach ([['attribute_key' => 'invalid'], ['skill_name' => 'Telepathie'], ['skill_name' => 'Unknown'], ['modifiers' => [['value' => 2, 'description' => ' ']]], ['character_id' => 9999999], ['revision' => 999], ['check_type' => 'attribute', 'skill_name' => 'Nahkampf']] as $change) {
        $invalid = $input;
        $invalid['participants'][0] = array_replace($invalid['participants'][0], $change);
        $this->postJson(route('rpg.checks.store'), $invalid)->assertUnprocessable();
    }
    $own = RpgCharacter::factory()->create(['user_id' => $this->leader->id, 'payload' => $this->progressionPayload()]);
    $input['participants'][0]['character_id'] = $own->id;
    $this->postJson(route('rpg.checks.store'), $input)->assertUnprocessable();
    $this->assertDatabaseCount('rpg_checks', 0);
});

test('no client dice and mismatched participant', function (): void {
    $check = $this->createCheck();
    $other = $this->createCheck();
    $this->actingAs($this->player)->postJson(route('rpg.checks.roll', [$check, $check->participants->first()]), ['dice' => [6, 6]])->assertUnprocessable();
    $this->postJson(route('rpg.checks.roll', [$check, $other->participants->first()]))->assertNotFound();
    expect($check->participants->first()->fresh()->rolled_at)->toBeNull();
});

test('stale preview and changed retry are rejected but snapshots survive improvements', function (): void {
    $input = $this->checkInput();
    $this->actingAs($this->leader)->postJson(route('rpg.checks.preview'), $input)->assertOk();
    $this->character->revision++;
    $this->character->save();
    $this->postJson(route('rpg.checks.store'), $input)->assertUnprocessable();
    $input['participants'][0]['revision'] = $this->character->revision;
    $this->postJson(route('rpg.checks.store'), $input)->assertOk();
    $changed = $input;
    $changed['difficulty']++;
    $this->postJson(route('rpg.checks.store'), $changed)->assertUnprocessable();
    $check = RpgCheck::firstOrFail()->load('participants');
    $payload = $this->character->payload;
    $payload['attributes']['ge'] = 2;
    $this->character->update(['payload' => $payload]);
    $this->dice([3, 4]);
    expect($this->rollCheck($check)->participants->first()->total)->toBe(9);
    $this->assertDatabaseCount('rpg_check_batches', 1);
});

test('untrained and missing attribute validation', function (): void {
    $input = $this->checkInput();
    $input['participants'][0]['skill_name'] = 'Heimlichkeit';
    $this->actingAs($this->leader)->postJson(route('rpg.checks.preview'), $input)->assertOk()->assertJsonPath('participants.0.skill_value', 0);
    $payload = $this->character->payload;
    unset($payload['attributes']['ge']);
    $this->character->update(['payload' => $payload]);
    $this->postJson(route('rpg.checks.preview'), $input)->assertUnprocessable();
});

test('attribute check uses tripled value and preserves negative modifiers', function (): void {
    $payload = $this->character->payload;
    $payload['attributes']['st'] = 1;
    $this->character->update(['payload' => $payload]);
    $input = $this->checkInput();
    $input['difficulty'] = 8;
    $input['participants'][0] = array_replace($input['participants'][0], ['check_type' => 'attribute', 'attribute_key' => 'st', 'skill_name' => null, 'modifiers' => [['value' => -2, 'description' => 'Schweres Gepäck']]]);
    $this->actingAs($this->leader)->postJson(route('rpg.checks.preview'), $input)->assertOk()->assertJsonPath('participants.0.base_total', 1);
    $check = $this->createCheck(input: $input);
    $this->dice([3, 4]);
    $result = $this->rollCheck($check);
    expect($result->participants[0]->total)->toBe(8)
        ->and($result->participants[0]->result_kind)->toBe('success')
        ->and($result->participants[0]->check->id)->toBe($check->id);
});

test('group settings duplicates and invalid decisions are rejected', function (): void {
    $input = $this->checkInput('opposed');
    $input['mode'] = 'fixed';
    $input['difficulty'] = 10;
    $input['participants'][1]['attribute_key'] = 'st';
    $this->actingAs($this->leader)->postJson(route('rpg.checks.store'), $input)->assertUnprocessable();
    $input['participants'][1] = $input['participants'][0];
    $this->postJson(route('rpg.checks.store'), $input)->assertUnprocessable();
    $check = $this->createCheck();
    $this->postJson(route('rpg.checks.resolve', $check), ['resolution' => 'side_1', 'reason' => 'Kein Gleichstand'])->assertUnprocessable();
    $this->postJson(route('rpg.checks.resolve', $check), ['resolution' => 'npc', 'reason' => 'Ungültig'])->assertUnprocessable();
    $this->postJson(route('rpg.checks.cancel', $check), ['reason' => '   '])->assertUnprocessable();
});

test('filters pagination and leadership change', function (): void {
    $open = $this->createCheck();
    $hidden = $this->createCheck(visibility: 'hidden');
    $this->actingAs($this->leader)->getJson(route('rpg.checks.index', ['visibility' => 'hidden']))->assertJsonPath('total', 1)->assertJsonPath('checks.0.id', $hidden->id);
    $this->getJson(route('rpg.checks.index', ['character_id' => $this->otherCharacter->id]))->assertJsonPath('total', 0);
    $this->getJson(route('rpg.checks.index', ['mode' => 'opposed']))->assertJsonPath('total', 0);
    $this->getJson(route('rpg.checks.index', ['tab' => 'history']))->assertJsonPath('total', 0);
    $this->getJson(route('rpg.checks.index', ['tab' => 'bad']))->assertUnprocessable();
    $newLeader = $this->progressionMember();
    $this->rpgTeam->users()->attach($newLeader, ['role' => 'Mitglied']);
    $this->rpgTeam->update(['user_id' => $newLeader->id]);
    $this->getJson(route('rpg.checks.show', $open))->assertNotFound();
    $this->actingAs($newLeader)->getJson(route('rpg.checks.show', $open))->assertOk();
    $this->rpgTeam->users()->detach($newLeader);
    $this->postJson(route('rpg.checks.store'), $this->checkInput())->assertForbidden();
});
