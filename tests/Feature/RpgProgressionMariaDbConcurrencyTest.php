<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\RpgAdvancementRequest;
use App\Models\RpgExperienceEntry;
use App\Services\RpgCharacterAdvancementService;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgProgressionMariaDbConcurrencyTestFixtures::class);

beforeEach(function (): void {
    if (getenv('DB_CONNECTION') !== 'mysql') {
        $this->markTestSkipped('Separate MariaDB-Konfiguration erforderlich.');
    }
    if (getenv('DB_DATABASE') !== 'omxfc_rpg_test') {
        throw new RuntimeException('Only the isolated omxfc_rpg_test database may be used.');
    }
    $this->progressionFixtures();
});

afterEach(function (): void {
    try {
        if ($this->committed) {
            $this->artisan('migrate:fresh')->assertExitCode(0);
        }
    } finally {
    }
});

test('simultaneous retries credit exactly once', function (): void {
    $input = ['action' => 'award', 'actor' => $this->leader->id, 'input' => $this->awardInput()];
    $results = $this->parallel($input, $input);
    expect($results[0]['ok'])->toBeTrue()
        ->and($results[1]['ok'])->toBeTrue()
        ->and($results[1]['id'])->toBe($results[0]['id'])
        ->and($this->character->experienceBalance())->toBe(60)
        ->and(RpgExperienceEntry::count())->toBe(1)
        ->and(Activity::where('action', Activity::ACTION_RPG_EXPERIENCE_AWARDED)->count())->toBe(1);
});

test('simultaneous submissions allow only one pending request', function (): void {
    $this->credit();
    $common = ['action' => 'submit', 'actor' => $this->player->id, 'character' => $this->character->id];
    $results = $this->parallel($common + ['input' => $this->advancementInput()], $common + ['input' => $this->advancementInput()]);
    expect(array_filter($results, fn ($result) => $result['ok']))->toHaveCount(1)
        ->and(RpgAdvancementRequest::where('status', 'pending')->count())->toBe(1)
        ->and($this->character->experienceBalance())->toBe(60);
});

test('simultaneous approvals debit and update exactly once', function (): void {
    $this->credit();
    $request = app(RpgCharacterAdvancementService::class)->submit($this->player, $this->character->id, $this->advancementInput());
    $input = ['action' => 'approved', 'actor' => $this->leader->id, 'request' => $request->id];
    $results = $this->parallel($input, $input);
    expect($results[0]['ok'])->toBeTrue()
        ->and($results[1]['ok'])->toBeTrue()
        ->and($this->character->experienceBalance())->toBe(54)
        ->and($this->character->fresh()->revision)->toBe(1)
        ->and(RpgExperienceEntry::where('amount', '<', 0)->count())->toBe(1);
});

test('approval and withdrawal have one terminal outcome', function (): void {
    $this->credit();
    $request = app(RpgCharacterAdvancementService::class)->submit($this->player, $this->character->id, $this->advancementInput());
    $results = $this->parallel(['action' => 'approved', 'actor' => $this->leader->id, 'request' => $request->id], ['action' => 'withdrawn', 'actor' => $this->player->id, 'request' => $request->id]);
    expect(array_filter($results, fn ($result) => $result['ok']))->toHaveCount(1);
    $approved = $request->fresh()->status === 'approved';
    expect($this->character->experienceBalance())->toBe($approved ? 54 : 60)
        ->and($this->character->fresh()->revision)->toBe($approved ? 1 : 0);
});

test('deletion racing with approval leaves no orphaned character bookings', function (): void {
    $this->credit();
    $request = app(RpgCharacterAdvancementService::class)->submit($this->player, $this->character->id, $this->advancementInput());
    $results = $this->parallel(['action' => 'approved', 'actor' => $this->leader->id, 'request' => $request->id], ['action' => 'delete', 'actor' => $this->player->id, 'character' => $this->character->id]);
    expect($results[1]['ok'])->toBeTrue()
        ->and($this->character->fresh())->toBeNull();
    $this->assertDatabaseCount('rpg_experience_entries', 0);
    $this->assertDatabaseCount('rpg_advancement_requests', 0);
    $this->assertDatabaseCount('rpg_experience_awards', 0);
});
