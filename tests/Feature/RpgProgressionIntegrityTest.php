<?php

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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RpgProgressionFixtures;
use Tests\TestCase;

class RpgProgressionIntegrityTest extends TestCase
{
    use RefreshDatabase, RpgProgressionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->progressionFixtures();
    }

    public function test_leader_is_independent_of_selected_team_and_changes_take_effect_immediately(): void
    {
        $access = app(RpgAccess::class);
        $this->assertNotSame($this->rpgTeam->id, $this->leader->current_team_id);
        $this->assertTrue($access->isLeader($this->leader));
        $this->assertFalse($access->isLeader($this->player));
        $this->rpgTeam->update(['user_id' => $this->player->id]);
        $this->assertFalse($access->isLeader($this->leader));
        $this->assertTrue($access->isLeader($this->player));
        $this->rpgTeam->users()->detach($this->player);
        $this->assertFalse($access->isLeader($this->player));
    }

    public function test_missing_or_ambiguous_ag_does_not_grant_access(): void
    {
        $access = app(RpgAccess::class);
        $this->rpgTeam->update(['name' => 'Andere AG']);
        $this->assertFalse($access->isLeader($this->leader));
        $this->rpgTeam->update(['name' => 'AG Rollenspiel']);
        Team::factory()->create(['name' => 'AG Rollenspiel', 'personal_team' => false]);
        $this->assertFalse($access->isLeader($this->leader));
        $this->assertFalse($access->isMember($this->player->id));
    }

    public function test_multiple_characters_of_same_owner_receive_separate_balances(): void
    {
        $second = RpgCharacter::factory()->create(['user_id' => $this->player->id]);
        $input = $this->awardInput(20);
        $input['participants'][] = array_replace($input['participants'][0], ['character_id' => $second->id, 'points' => 40]);
        app(RpgExperienceAwardService::class)->award($this->leader, $input);
        $this->assertSame(20, $this->character->experienceBalance());
        $this->assertSame(40, $second->experienceBalance());
        $this->assertDatabaseCount('rpg_experience_entries', 2);
    }

    public function test_invalid_participant_rolls_back_the_whole_adventure(): void
    {
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
    }

    public static function staleRequests(): array
    {
        return [['rule_version'], ['after_payload'], ['cost'], ['membership'], ['balance'], ['raw_payload']];
    }

    #[DataProvider('staleRequests')]
    public function test_approval_revalidates_every_dependency_without_partial_writes(string $changed): void
    {
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
            $this->assertSame('pending', $request->fresh()->status);
            $this->assertSame($balance, $this->character->experienceBalance());
            $this->assertSame($original, $this->character->fresh()->payload);
            $this->assertSame(0, $this->character->fresh()->revision);
        }
    }

    public function test_failure_after_debit_rolls_back_debit_payload_and_status(): void
    {
        $this->credit();
        $service = app(RpgCharacterAdvancementService::class);
        $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
        $original = $this->character->payload;
        RpgCharacter::updating(fn () => throw new \RuntimeException('Simulated storage failure'));
        try {
            $service->decide($this->leader, $request->id, 'approved');
            $this->fail('Expected storage failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated storage failure', $exception->getMessage());
            $this->assertSame(60, $this->character->experienceBalance());
            $this->assertSame($original, $this->character->fresh()->payload);
            $this->assertSame('pending', $request->fresh()->status);
        } finally {
            RpgCharacter::flushEventListeners();
            RpgCharacter::clearBootedModels();
        }
    }

    public function test_changed_submission_key_replay_is_rejected(): void
    {
        $this->credit();
        $service = app(RpgCharacterAdvancementService::class);
        $input = $this->advancementInput();
        $service->submit($this->player, $this->character->id, $input);
        $input['operations'][0]['steps'] = 2;
        $this->expectException(ValidationException::class);
        $service->submit($this->player, $this->character->id, $input);
    }

    public function test_deleting_an_actor_preserves_other_characters_ledger_and_audit(): void
    {
        $this->credit();
        $service = app(RpgCharacterAdvancementService::class);
        $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
        $service->decide($this->leader, $request->id, 'approved');
        $this->leader->delete();
        $this->assertNull($request->fresh()->reviewer_id);
        $this->assertNull(RpgAdventure::firstOrFail()->user_id);
        $history = app(RpgProgressionHistory::class)->forCharacter($this->character);
        $this->assertSame(54, $history['balance']);
        $this->assertSame('Ehemaliges Mitglied', $history['entries'][0]['actor']);
        $this->assertSame('Ehemaliges Mitglied', $history['entries'][1]['actor']);
    }

    public function test_journal_orders_backdated_adventures_by_actual_booking_and_reconstructs_state(): void
    {
        $this->credit();
        $service = app(RpgCharacterAdvancementService::class);
        $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
        $service->decide($this->leader, $request->id, 'approved');
        $input = array_replace($this->awardInput(3), ['completed_on' => '2007-01-01']);
        app(RpgExperienceAwardService::class)->award($this->leader, $input);
        $history = app(RpgProgressionHistory::class)->forCharacter($this->character);
        $this->assertSame([60, 54, 57], array_column($history['entries'], 'balance'));
        $this->assertSame(63, $history['received']);
        $this->assertSame(6, $history['spent']);
        $this->assertSame('01.01.2007', $history['entries'][2]['completed_on']);
        $this->assertSame($this->character->initial_payload, $request->before_payload);
        $this->assertSame($request->after_payload, $this->character->fresh()->payload);
    }

    public static function invalidEntries(): array
    {
        return [['no_source'], ['both_sources'], ['foreign_character'], ['wrong_amount']];
    }

    #[DataProvider('invalidEntries')]
    public function test_ledger_rejects_invalid_source_or_amount(string $case): void
    {
        $this->credit();
        $existing = RpgExperienceEntry::firstOrFail();
        $attributes = ['rpg_character_id' => $this->character->id, 'rpg_experience_award_id' => $existing->rpg_experience_award_id, 'amount' => 60];
        match ($case) {
            'no_source' => $attributes['rpg_experience_award_id'] = null,
            'both_sources' => $attributes['rpg_advancement_request_id'] = 99,
            'foreign_character' => $attributes['rpg_character_id'] = RpgCharacter::factory()->create()->id,
            'wrong_amount' => $attributes['amount'] = -60,
        };
        $this->expectException(\LogicException::class);
        RpgExperienceEntry::create($attributes);
    }

    public function test_initial_character_snapshot_is_immutable(): void
    {
        $this->expectException(\LogicException::class);
        $this->character->forceFill(['initial_payload' => ['forged' => true]])->save();
    }

    public function test_booked_amount_is_immutable(): void
    {
        $this->credit();
        $this->expectException(\LogicException::class);
        RpgExperienceEntry::firstOrFail()->update(['amount' => 999]);
    }

    public function test_migration_preserves_existing_payload_portrait_and_zero_initial_balance(): void
    {
        $migration = require database_path('migrations/2026_09_26_120000_add_rpg_progression.php');
        $original = DB::table('rpg_characters')->find($this->character->id);
        $migration->down();
        $migration->up();
        $migrated = DB::table('rpg_characters')->find($this->character->id);
        $this->assertSame($original->payload, $migrated->payload);
        $this->assertSame($original->payload, $migrated->initial_payload);
        $this->assertSame($original->portrait_path, $migrated->portrait_path);
        $this->assertSame(0, $migrated->revision);
        $this->assertSame(0, $this->character->experienceBalance());
    }
}
