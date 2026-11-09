<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgAdventure;
use App\Models\RpgCharacter;
use App\Models\RpgExperienceEntry;
use App\Models\Team;
use App\Services\RpgAccess;
use App\Services\RpgCharacterAdvancementService;
use App\Services\RpgExperienceAwardService;
use App\Services\RpgProgressionHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\RpgProgressionFixtures;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class, RpgProgressionFixtures::class);
dataset('RpgProgressionIntegrityTest.staleRequests', static fn (): array => [['rule_version'], ['after_payload'], ['cost'], ['membership'], ['balance'], ['raw_payload']]);
dataset('RpgProgressionIntegrityTest.invalidEntries', static fn (): array => [['no_source'], ['both_sources'], ['foreign_character'], ['wrong_amount']]);
beforeEach(function (): void {
    $this->progressionFixtures();
});
test('leader is independent of selected team and changes take effect immediately', function (): void {
    $access = app(RpgAccess::class);
    expect($this->leader->current_team_id)->not->toBe($this->rpgTeam->id)
        ->and($access->isLeader($this->leader))->toBeTrue()
        ->and($access->isLeader($this->player))->toBeFalse();
    $this->rpgTeam->update(['user_id' => $this->player->id]);
    expect($access->isLeader($this->leader))->toBeFalse()
        ->and($access->isLeader($this->player))->toBeTrue();
    $this->rpgTeam->users()->detach($this->player);
    expect($access->isLeader($this->player))->toBeFalse();
});
test('missing or ambiguous ag does not grant access', function (): void {
    $access = app(RpgAccess::class);
    $this->rpgTeam->update(['name' => 'Andere AG']);
    expect($access->isLeader($this->leader))->toBeFalse();
    $this->rpgTeam->update(['name' => 'AG Rollenspiel']);
    Team::factory()->create(['name' => 'AG Rollenspiel', 'personal_team' => false]);
    expect($access->isLeader($this->leader))->toBeFalse()
        ->and($access->isMember($this->player->id))->toBeFalse();
});
test('multiple characters of same owner receive separate balances', function (): void {
    $second = RpgCharacter::factory()->create(['user_id' => $this->player->id]);
    $input = $this->awardInput(20);
    $input['participants'][] = array_replace($input['participants'][0], ['character_id' => $second->id, 'points' => 40]);
    app(RpgExperienceAwardService::class)->award($this->leader, $input);
    expect($this->character->experienceBalance())->toBe(20)
        ->and($second->experienceBalance())->toBe(40);
    $this->assertDatabaseCount('rpg_experience_entries', 2);
});
test('invalid participant rolls back the whole adventure', function (): void {
    $outsider = RpgCharacter::factory()->create();
    $input = $this->awardInput();
    $input['participants'][] = array_replace($input['participants'][0], ['character_id' => $outsider->id]);
    try {
        app(RpgExperienceAwardService::class)->award($this->leader, $input);
        $this->fail('Expected membership validation');
    } catch (ValidationException) {
        $this->assertDatabaseCount('rpg_adventures', 0);
        $this->assertDatabaseCount('rpg_experience_entries', 0);
    }
});
test('approval revalidates every dependency without partial writes', function (string $changed): void {
    $this->credit();
    $service = app(RpgCharacterAdvancementService::class);
    $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
    match ($changed) {
        'rule_version' => $request->update(['rule_version' => 'future']),
        'after_payload' => $request->update(['after_payload' => []]),
        'cost' => $request->update(['cost' => 1]),
        'membership' => $this->rpgTeam->users()->detach($this->player),
        'balance' => DB::table('rpg_experience_entries')->update(['amount' => 0]),
        'raw_payload' => $this->character->update(['payload' => $this->character->payload + ['notes' => 'changed outside progression']]),
    };
    $original = $this->character->fresh()->payload;
    $balance = $this->character->experienceBalance();
    try {
        $service->decide($this->leader, $request->id, 'approved');
        $this->fail('Expected stale request validation');
    } catch (ValidationException) {
        expect($request->fresh()->status)->toBe('pending')
            ->and($this->character->experienceBalance())->toBe($balance)
            ->and($this->character->fresh()->payload)->toBe($original)
            ->and($this->character->fresh()->revision)->toBe(0);
    }
})->with('RpgProgressionIntegrityTest.staleRequests');
test('failure after debit rolls back debit payload and status', function (): void {
    $this->credit();
    $service = app(RpgCharacterAdvancementService::class);
    $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
    $original = $this->character->payload;
    RpgCharacter::updating(fn () => throw new \RuntimeException('Simulated storage failure'));
    try {
        $service->decide($this->leader, $request->id, 'approved');
        $this->fail('Expected storage failure');
    } catch (\RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Simulated storage failure')
            ->and($this->character->experienceBalance())->toBe(60)
            ->and($this->character->fresh()->payload)->toBe($original)
            ->and($request->fresh()->status)->toBe('pending');
    } finally {
        RpgCharacter::flushEventListeners();
        RpgCharacter::clearBootedModels();
    }
});
test('changed submission key replay is rejected', function (): void {
    $this->credit();
    $service = app(RpgCharacterAdvancementService::class);
    $input = $this->advancementInput();
    $service->submit($this->player, $this->character->id, $input);
    $input['operations'][0]['steps'] = 2;
    expect(fn () => $service->submit($this->player, $this->character->id, $input))->toThrow(ValidationException::class);
});
test('deleting an actor preserves other characters ledger and audit', function (): void {
    $this->credit();
    $service = app(RpgCharacterAdvancementService::class);
    $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
    $service->decide($this->leader, $request->id, 'approved');
    $this->leader->delete();
    expect($request->fresh()->reviewer_id)->toBeNull()
        ->and(RpgAdventure::firstOrFail()->user_id)->toBeNull();
    $history = app(RpgProgressionHistory::class)->forCharacter($this->character);
    expect($history['balance'])->toBe(54)
        ->and($history['entries'][0]['actor'])->toBe('Ehemaliges Mitglied')
        ->and($history['entries'][1]['actor'])->toBe('Ehemaliges Mitglied');
});
test('journal orders backdated adventures by actual booking and reconstructs state', function (): void {
    $this->credit();
    $service = app(RpgCharacterAdvancementService::class);
    $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
    $service->decide($this->leader, $request->id, 'approved');
    $input = array_replace($this->awardInput(3), ['completed_on' => '2007-01-01']);
    app(RpgExperienceAwardService::class)->award($this->leader, $input);
    $history = app(RpgProgressionHistory::class)->forCharacter($this->character);
    expect(array_column($history['entries'], 'balance'))->toBe([60, 54, 57])
        ->and($history['received'])->toBe(63)
        ->and($history['spent'])->toBe(6)
        ->and($history['entries'][2]['completed_on'])->toBe('01.01.2007')
        ->and($request->before_payload)->toBe($this->character->initial_payload)
        ->and($this->character->fresh()->payload)->toBe($request->after_payload);
});
test('ledger rejects invalid source or amount', function (string $case): void {
    $this->credit();
    $existing = RpgExperienceEntry::firstOrFail();
    $attributes = ['rpg_character_id' => $this->character->id, 'rpg_experience_award_id' => $existing->rpg_experience_award_id, 'amount' => 60];
    match ($case) {
        'no_source' => $attributes['rpg_experience_award_id'] = null,
        'both_sources' => $attributes['rpg_advancement_request_id'] = 99,
        'foreign_character' => $attributes['rpg_character_id'] = RpgCharacter::factory()->create()->id,
        'wrong_amount' => $attributes['amount'] = -60,
    };
    expect(fn () => RpgExperienceEntry::create($attributes))->toThrow(\LogicException::class);
})->with('RpgProgressionIntegrityTest.invalidEntries');
test('initial character snapshot is immutable', function (): void {
    expect(fn () => $this->character->forceFill(['initial_payload' => ['forged' => true]])->save())->toThrow(\LogicException::class);
});
test('booked amount is immutable', function (): void {
    $this->credit();
    expect(fn () => RpgExperienceEntry::firstOrFail()->update(['amount' => 999]))->toThrow(\LogicException::class);
});
test('migration preserves existing payload portrait and zero initial balance', function (): void {
    $migration = require database_path('migrations/2026_09_26_120000_add_rpg_progression.php');
    $original = DB::table('rpg_characters')->find($this->character->id);
    $migration->down();
    $migration->up();
    $migrated = DB::table('rpg_characters')->find($this->character->id);
    expect($migrated->payload)->toBe($original->payload)
        ->and($migrated->initial_payload)->toBe($original->payload)
        ->and($migrated->portrait_path)->toBe($original->portrait_path)
        ->and($migrated->revision)->toBe(0)
        ->and($this->character->experienceBalance())->toBe(0);
});
