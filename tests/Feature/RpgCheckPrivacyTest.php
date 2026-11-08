<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\RpgCheckQuery;
use App\Services\RpgCheckService;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgCheckPrivacyTestFixtures::class);

beforeEach(function (): void {
    $this->checkFixtures();
});

test('hidden result is absent from every player response', function (): void {
    $input = $this->checkInput(visibility: 'hidden');
    $input['difficulty'] = 743;
    $input['participants'][0]['modifiers'] = [['value' => 913, 'description' => 'SECRET-SITUATION']];
    $check = $this->createCheck(input: $input);
    $this->dice([6, 6]);
    $this->actingAs($this->player);
    // Deterministically exercise the former false positive from page tokens.
    $this->withSession(['_token' => 'csrf-token-743-913']);
    $projection = $this->getJson(route('rpg.checks.show', $check))->assertOk()->json();
    $html = $this->get(route('rpg.checks.show', $check))->assertSee('csrf-token-743-913');
    $this->assertSecretHtml($html, $projection);
    $response = $this->postJson(route('rpg.checks.roll', [$check, $check->participants[0]]))->assertOk();
    $this->assertSecret($response->json());
    $this->assertSecretHtml($this->get(route('rpg.checks.show', $check)), $response->json());
    $this->assertSecret($this->getJson(route('rpg.checks.index', ['tab' => 'history']))->json('checks.0'));
    $this->get(route('dashboard'))->assertOk()->assertDontSee('SECRET-SITUATION');
    $this->actingAs($this->leader)->getJson(route('rpg.checks.show', $check))->assertJsonPath('participants.0.dice', [6, 6])->assertJsonPath('difficulty', 743);
});

test('hidden winner tie and decision have identical player projection', function (): void {
    $check = $this->createCheck('opposed', 'hidden');
    $this->dice([3, 4], [4, 3]);
    $this->rollCheck($check);
    $query = app(RpgCheckQuery::class);
    $before = $query->detail($this->player, $check);
    $this->rollCheck($check, $this->otherPlayer, 2);
    expect($query->detail($this->player, $check))->toBe($before);
    app(RpgCheckService::class)->resolve($this->leader, $check->id, 'side_2', 'SECRET-DECISION');
    expect($query->detail($this->player, $check))->toBe($before);
    $this->assertSecret($before);
    expect($query->listing($this->player, hints: true)['total'])->toBe(0);
});

test('open comparison never exposes opponents numbers', function (): void {
    $check = $this->createCheck('opposed');
    $this->dice([5, 6], [1, 2]);
    $this->rollCheck($check);
    $this->rollCheck($check, $this->otherPlayer, 2);
    $data = app(RpgCheckQuery::class)->detail($this->player, $check);
    expect($data['participants'][0]['total'])->toBe(13)
        ->and(array_keys($data['participants'][1]))->toBe(['id', 'position', 'character_name'])
        ->and($data['winner_position'])->toBe(1)
        ->and($data)->not->toHaveKey('comparison_margin')
        ->and($data['participants'][0])->not->toHaveKey('margin');
});
