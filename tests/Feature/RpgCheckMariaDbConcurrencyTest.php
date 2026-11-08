<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgCheck;
use App\Models\RpgCheckParticipant;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgCheckMariaDbConcurrencyTestFixtures::class);

beforeEach(function (): void {
    if (getenv('DB_CONNECTION') !== 'mysql') {
        $this->markTestSkipped('Separate MariaDB-Konfiguration erforderlich.');
    }
    if (getenv('DB_DATABASE') !== 'omxfc_rpg_test') {
        throw new RuntimeException('Only the isolated omxfc_rpg_test database may be used.');
    }
    $this->checkFixtures();
});

afterEach(function (): void {
    try {
        if ($this->committed) {
            $this->artisan('migrate:fresh')->assertExitCode(0);
        }
    } finally {
    }
});

test('parallel duplicate rolls persist one result', function (): void {
    $check = $this->createCheck();
    $input = ['action' => 'roll', 'actor' => $this->player->id, 'check' => $check->id, 'participant' => $check->participants[0]->id];
    $results = $this->parallel($input, $input);
    expect($results[0]['ok'])->toBeTrue()
        ->and($results[1]['ok'])->toBeTrue()
        ->and(RpgCheckParticipant::whereNotNull('rolled_at')->count())->toBe(1)
        ->and($check->fresh()->participants[0]->total)->toBe(9);
});

test('parallel sides complete one comparison', function (): void {
    $check = $this->createCheck('opposed');
    $results = $this->parallel(['action' => 'roll', 'actor' => $this->player->id, 'check' => $check->id, 'participant' => $check->participants[0]->id], ['action' => 'roll', 'actor' => $this->otherPlayer->id, 'check' => $check->id, 'participant' => $check->participants[1]->id]);
    expect($results[0]['ok'])->toBeTrue()
        ->and($results[1]['ok'])->toBeTrue()
        ->and($check->fresh()->status)->toBe('awaiting_decision')
        ->and(RpgCheckParticipant::whereNotNull('rolled_at')->count())->toBe(2);
});

test('cancel and roll have a consistent terminal state', function (): void {
    $check = $this->createCheck();
    $results = $this->parallel(['action' => 'roll', 'actor' => $this->player->id, 'check' => $check->id, 'participant' => $check->participants[0]->id], ['action' => 'cancel', 'actor' => $this->leader->id, 'check' => $check->id]);
    expect(array_filter($results, fn ($r) => $r['ok']))->toHaveCount(1);
    $fresh = $check->fresh();
    expect(['completed', 'cancelled'])->toContain($fresh->status)
        ->and($fresh->participants[0]->rolled_at !== null)->toBe($fresh->status === 'completed');
});

test('deletion and roll leave no open orphan', function (): void {
    $check = $this->createCheck('opposed');
    $results = $this->parallel(['action' => 'roll', 'actor' => $this->player->id, 'check' => $check->id, 'participant' => $check->participants[0]->id], ['action' => 'delete', 'actor' => $this->player->id, 'character' => $this->character->id]);
    expect($results[1]['ok'])->toBeTrue()
        ->and($check->fresh()->status)->toBe('cancelled')
        ->and($check->fresh()->participants[0]->rpg_character_id)->toBeNull();
});

test('parallel batch retries do not duplicate requests', function (): void {
    $input = $this->checkInput('opposed');
    $input['mode'] = 'fixed';
    $input['difficulty'] = 10;
    $request = ['action' => 'create', 'actor' => $this->leader->id, 'input' => $input];
    $results = $this->parallel($request, $request);
    expect($results[0]['ok'])->toBeTrue()
        ->and($results[1]['ok'])->toBeTrue()
        ->and($results[1]['id'])->toBe($results[0]['id'])
        ->and(RpgCheck::count())->toBe(2)
        ->and(RpgCheckParticipant::count())->toBe(2);
});

test('conflicting tie decisions are serialized', function (): void {
    $check = $this->createCheck('opposed');
    $this->dice([3, 4], [4, 3]);
    $this->rollCheck($check);
    $this->rollCheck($check, $this->otherPlayer, 2);
    $common = ['action' => 'resolve', 'actor' => $this->leader->id, 'check' => $check->id];
    $results = $this->parallel($common + ['resolution' => 'side_1'], $common + ['resolution' => 'side_2']);
    expect(array_filter($results, fn ($r) => $r['ok']))->toHaveCount(1)
        ->and($check->fresh()->status)->toBe('completed')
        ->and([1, 2])->toContain($check->fresh()->winner_position);
});
