<?php

namespace Tests\Feature;

use App\Jobs\SendRpgCombatMail;
use App\Mail\RpgCombatMail;
use App\Models\RpgCombat;
use App\Models\RpgCombatDelivery;
use App\Models\RpgCombatMilestone;
use App\Models\RpgNpc;
use App\Services\RpgCombat\CombatQuery;
use App\Services\RpgCombat\CombatService;
use App\Services\RpgNpcService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RpgCombatFixtures;
use Tests\TestCase;

class RpgNpcCombatTest extends TestCase
{
    use RefreshDatabase, RpgCombatFixtures;

    private RpgNpc $npc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->combatFixtures();
        Mail::fake();
        $this->npc = app(RpgNpcService::class)->create($this->leader, ['submission_key' => (string) Str::uuid(), 'template_key' => 'androne']);
    }

    private function invitation(array $extra = []): RpgCombat
    {
        return app(CombatService::class)->challenge($this->leader, array_replace(['kind' => 'npc_vs_player', 'npc_id' => $this->npc->id,
            'npc_revision' => $this->npc->revision, 'opponent_id' => $this->character->id, 'opponent_revision' => $this->character->revision,
            'distance' => 1, 'submission_key' => (string) Str::uuid()], $extra));
    }

    private function accepted(): RpgCombat
    {
        return app(CombatService::class)->command($this->player, $this->invitation()->id, 'accept', (string) Str::uuid());
    }

    private function step(RpgCombat $combat, string $type, int $side, array $input = []): RpgCombat
    {
        $decision = $combat->decisions()->where('status', 'pending')->where('type', $type)->where('side', $side)->sole();

        return app(CombatService::class)->decide($side === 2 ? $this->player : $this->leader, $combat->id, $decision->id, $input);
    }

    private function active(): RpgCombat
    {
        $combat = $this->accepted();
        foreach ([1, 2] as $side) {
            $combat = $this->step($combat, 'prepare', $side, ['weapons' => [], 'shield' => false, 'skill' => 'Nahkampf']);
        }
        $this->combatDice->values = [6, 1];
        foreach ([1, 2] as $side) {
            $combat = $this->step($combat, 'initiative', $side);
        }

        return $combat;
    }

    public function test_invitation_and_acceptance_have_npc_snapshots_locks_dashboard_and_notifications(): void
    {
        $key = (string) Str::uuid();
        $combat = $this->invitation(['submission_key' => $key]);
        $this->assertSame($combat->id, $this->invitation(['submission_key' => $key])->id);
        $this->assertSame('npc_vs_player', $combat->kind);
        $this->assertSame('npc', $combat->participants[0]->participant_kind);
        $this->assertNull($combat->participants[0]->owner_id);
        $this->assertDatabaseCount('rpg_combat_npc_locks', 0);
        $this->assertStringContainsString('Androne fordert', RpgCombatMilestone::firstOrFail()->message());
        $this->actingAs($this->leader)->get(route('rpg.combats.create', ['npc_id' => $this->npc->id]))->assertOk()->assertSee('NSC herausfordern');
        $this->actingAs($this->player)->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('Ablehnen');
        $combat = app(CombatService::class)->command($this->player, $combat->id, 'accept', (string) Str::uuid());
        $this->assertDatabaseCount('rpg_combat_npc_locks', 1);
        $this->assertDatabaseCount('rpg_combat_character_locks', 1);
        $npcDecision = $combat->decisions()->where('side', 1)->sole();
        $this->assertSame('manual_leader', $npcDecision->timeout_policy);
        $this->assertNull($npcDecision->due_at);
        $this->assertNotNull($npcDecision->reminder_at);
        $html = $this->actingAs($this->leader)->getJson(route('rpg.combats.show', $combat))->assertOk()->json('html');
        $this->assertStringContainsString('persönlich', $html);
        $this->assertCount(1, app(CombatQuery::class)->hints($this->leader));
        $this->assertSame($this->progressionPayload(), $this->character->fresh()->payload);
        foreach (RpgCombatDelivery::all() as $delivery) {
            (new SendRpgCombatMail($delivery->id))->handle();
        }
        Mail::assertSent(RpgCombatMail::class, fn ($mail) => $mail->hasTo($this->leader->email));
        Mail::assertSent(RpgCombatMail::class, fn ($mail) => $mail->hasTo($this->player->email));
    }

    public static function cancellationCommands(): array
    {
        return ['decline' => ['decline', 2, 'declined'], 'withdraw' => ['withdraw', 1, 'withdrawn']];
    }

    #[DataProvider('cancellationCommands')]
    public function test_unaccepted_invitation_can_be_closed_without_effects(string $command, int $side, string $status): void
    {
        $combat = $this->invitation();
        $user = $side === 1 ? $this->leader : $this->player;
        $key = (string) Str::uuid();
        app(CombatService::class)->command($user, $combat->id, $command, $key);
        app(CombatService::class)->command($user, $combat->id, $command, $key);
        $this->assertSame($status, $combat->fresh()->status);
        $this->assertDatabaseCount('rpg_combat_npc_locks', 0);
        $this->assertSame(0, $this->combatDice->calls);
        $this->assertDatabaseCount('rpg_combat_milestones', 1);
    }

    public function test_complete_combat_and_retries_never_roll_twice_or_change_originals(): void
    {
        $combat = $this->active();
        $profile = $this->npc->profile;
        $this->combatDice->values = [6, 6, 1, 1, 6];
        $decision = $combat->decisions()->where('status', 'pending')->sole();
        $input = ['kind' => 'attack', 'weapon' => 'body:0'];
        $combat = app(CombatService::class)->decide($this->leader, $combat->id, $decision->id, $input);
        $calls = $this->combatDice->calls;
        app(CombatService::class)->decide($this->leader, $combat->id, $decision->id, $input);
        $this->assertSame($calls, $this->combatDice->calls);
        $combat = $this->step($combat, 'defense', 2, ['defense' => 'dodge']);
        $combat = $this->step($combat, 'damage', 1);
        $this->assertSame('completed', $combat->status);
        $this->assertSame(1, $combat->winner_side);
        $this->assertDatabaseCount('rpg_combat_npc_locks', 0);
        $this->assertDatabaseCount('rpg_combat_character_locks', 0);
        $this->assertSame($profile, $this->npc->fresh()->profile);
        $this->assertSame($this->progressionPayload(), $this->character->fresh()->payload);
        $this->assertSame(0, $this->character->experienceBalance());
        $this->assertSame(3, RpgCombatMilestone::count());
    }

    public function test_npc_and_sl_inputs_never_timeout_but_remind_once_and_accept_late_input(): void
    {
        $combat = $this->accepted();
        $npcDecision = $combat->decisions()->where('side', 1)->sole();
        $playerDecision = $combat->decisions()->where('side', 2)->sole();
        $this->travel(25)->hours();
        app(CombatService::class)->decide(null, $combat->id, $npcDecision->id);
        $this->assertSame('pending', $npcDecision->fresh()->status);
        $this->artisan('rpg:process-combats')->assertSuccessful();
        $this->artisan('rpg:process-combats')->assertSuccessful();
        $this->assertTrue($playerDecision->fresh()->automatic);
        $this->assertSame('pending', $npcDecision->fresh()->status);
        $this->assertFalse($npcDecision->fresh()->automatic);
        $this->assertSame(1, RpgCombatDelivery::where('kind', 'reminder')->count());
        $this->assertNotNull($npcDecision->fresh()->reminded_at);
        $this->step($combat, 'prepare', 1, ['weapons' => [], 'shield' => false, 'skill' => 'Nahkampf']);
        $this->assertFalse($npcDecision->fresh()->automatic);
    }

    public function test_manual_ruling_pauses_player_deadline_and_remains_open_after_days(): void
    {
        $this->npc = app(RpgNpcService::class)->create($this->leader, ['submission_key' => (string) Str::uuid(), 'template_key' => 'bandit']);
        $combat = $this->accepted();
        $playerDecision = $combat->decisions()->where('side', 2)->sole();
        $this->travel(3)->hours();
        $combat = $this->step($combat, 'prepare', 1, ['weapons' => ['schwert:1'], 'shield' => false, 'skill' => 'Nahkampf']);
        $this->assertSame('paused', $playerDecision->fresh()->status);
        $pausedMail = RpgCombatDelivery::where('rpg_combat_decision_id', $playerDecision->id)->sole();
        (new SendRpgCombatMail($pausedMail->id))->handle();
        $this->assertSame('cancelled', $pausedMail->fresh()->status);
        $remaining = $playerDecision->fresh()->remaining_seconds;
        $this->travel(3)->days();
        $this->artisan('rpg:process-combats')->assertSuccessful();
        $this->assertSame('awaiting_ruling', $combat->fresh()->status);
        $this->assertSame('paused', $playerDecision->fresh()->status);
        $combat = $this->step($combat, 'ruling', 0, ['choices' => ['hands' => 'catalog'], 'reason' => 'Gedruckte Handbelegung']);
        $this->assertSame('pending', $playerDecision->fresh()->status);
        $this->assertSame('pending', $pausedMail->fresh()->status);
        $this->assertSame(now('UTC')->addSeconds($remaining)->timestamp, $playerDecision->fresh()->due_at->timestamp);
        $this->assertSame(0, $combat->state['seconds']);
    }

    public function test_handover_revokes_old_authority_and_cancels_stale_mail(): void
    {
        $combat = $this->accepted();
        $decision = $combat->decisions()->where('side', 1)->sole();
        $mail = RpgCombatDelivery::where('rpg_combat_decision_id', $decision->id)->sole();
        $reminder = $decision->reminder_at->timestamp;
        $this->rpgTeam->update(['user_id' => $this->opponent->id]);
        $this->assertSame($this->opponent->id, $combat->fresh()->leader_id);
        $this->assertDatabaseHas('rpg_combat_deliveries', ['rpg_combat_id' => $combat->id, 'kind' => 'handover', 'recipient_id' => $this->opponent->id]);
        $this->assertSame($reminder, $decision->fresh()->reminder_at->timestamp);
        $this->actingAs($this->leader)->postJson(route('rpg.combats.decide', [$combat, $decision->id]), ['weapons' => [], 'shield' => false, 'skill' => 'Nahkampf'])->assertForbidden();
        (new SendRpgCombatMail($mail->id))->handle();
        $this->assertSame('cancelled', $mail->fresh()->status);
        $newMail = RpgCombatDelivery::where('rpg_combat_decision_id', $decision->id)->where('recipient_id', $this->opponent->id)->sole();
        (new SendRpgCombatMail($newMail->id))->handle();
        Mail::assertSent(RpgCombatMail::class, fn ($m) => $m->hasTo($this->opponent->email));
        $this->actingAs($this->opponent)->post(route('rpg.combats.decide', [$combat, $decision->id]), ['weapons' => [], 'shield' => '0', 'skill' => 'Nahkampf'])->assertRedirect();
    }

    public function test_missing_active_leader_suspends_and_restores_remaining_deadlines(): void
    {
        $combat = $this->accepted();
        $this->travel(2)->hours();
        $this->rpgTeam->users()->detach($this->leader);
        app(CombatService::class)->sweep($combat->id);
        $this->assertNotNull($combat->fresh()->suspension);
        $this->assertSame(2, $combat->decisions()->where('status', 'paused')->count());
        $player = $combat->decisions()->where('side', 2)->sole();
        $remaining = $player->remaining_seconds;
        $this->travel(2)->days();
        $this->artisan('rpg:process-combats')->assertSuccessful();
        $this->assertSame(0, $this->combatDice->calls);
        $this->rpgTeam->update(['user_id' => $this->opponent->id]);
        $this->assertNull($combat->fresh()->suspension);
        $this->assertSame(now('UTC')->addSeconds($remaining)->timestamp, $player->fresh()->due_at->timestamp);
    }

    public function test_new_leader_may_control_both_sides_of_an_existing_combat(): void
    {
        $combat = $this->accepted();
        $this->rpgTeam->update(['user_id' => $this->player->id]);
        $detail = app(CombatQuery::class)->detail($this->player, $combat->fresh());
        $this->assertSame([1, 2], $detail['sides']);
        $this->actingAs($this->player)->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('name="side" value="1"', false)->assertSee('name="side" value="2"', false);
        $this->post(route('rpg.combats.command', $combat), ['command' => 'abort', 'side' => 1, 'submission_key' => (string) Str::uuid()])->assertRedirect();
        $this->post(route('rpg.combats.command', $combat), ['command' => 'abort', 'side' => 2, 'submission_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('cancelled', $combat->fresh()->result);
    }

    public function test_busy_instance_stale_snapshots_and_membership_cleanup(): void
    {
        $combat = $this->invitation();
        $this->actingAs($this->leader)->deleteJson(route('rpg.npcs.destroy', $this->npc))->assertUnprocessable();
        app(RpgNpcService::class)->rename($this->leader, $this->npc, ['revision' => 0, 'custom_name' => 'Umbenannt']);
        $this->actingAs($this->player)->postJson(route('rpg.combats.command', $combat), ['command' => 'accept', 'submission_key' => (string) Str::uuid()])->assertUnprocessable();
        app(CombatService::class)->command($this->player, $combat->id, 'decline', (string) Str::uuid());
        $this->npc->refresh();
        $combat = $this->accepted();
        $this->actingAs($this->leader)->patchJson(route('rpg.npcs.rename', $this->npc), ['revision' => 1, 'custom_name' => 'Busy'])->assertUnprocessable();
        $this->rpgTeam->users()->detach($this->player);
        app(CombatService::class)->sweep($combat->id);
        $this->assertSame('membership_changed', $combat->fresh()->result);
        $this->assertDatabaseCount('rpg_combat_npc_locks', 0);
    }

    public function test_snapshot_tampering_is_rejected_before_invitation(): void
    {
        DB::table('rpg_npcs')->where('id', $this->npc->id)->update(['profile_hash' => str_repeat('0', 64)]);
        $this->expectException(ValidationException::class);
        $this->invitation();
    }

    public function test_only_leader_can_decide_npc_and_only_owner_can_accept(): void
    {
        $combat = $this->invitation();
        $this->actingAs($this->leader)->postJson(route('rpg.combats.command', $combat), ['command' => 'accept', 'submission_key' => (string) Str::uuid()])->assertForbidden();
        $this->actingAs($this->opponent)->get(route('rpg.combats.show', $combat))->assertForbidden();
        $combat = app(CombatService::class)->command($this->player, $combat->id, 'accept', (string) Str::uuid());
        $decision = $combat->decisions()->where('side', 1)->sole();
        $this->actingAs($this->player)->postJson(route('rpg.combats.decide', [$combat, $decision->id]), ['weapons' => [], 'shield' => false, 'skill' => 'Nahkampf'])->assertForbidden();
        $this->actingAs($this->leader)->postJson(route('rpg.combats.decide', [$combat, $decision->id]), ['dice' => [6, 6]])->assertUnprocessable();
        $this->assertSame(0, $this->combatDice->calls);
    }

    public function test_mixed_participant_identity_is_rejected_by_database(): void
    {
        $combat = $this->invitation();
        $this->expectException(QueryException::class);
        DB::table('rpg_combat_participants')->where('rpg_combat_id', $combat->id)->where('side', 1)->update(['rpg_character_id' => $this->character->id]);
    }

    public function test_database_defaults_preserve_historical_pvp_and_npc_migration_can_be_reversed(): void
    {
        $combat = $this->combat(false);
        $this->assertSame('player_vs_player', $combat->kind);
        $this->assertSame(['player', 'player'], $combat->participants->pluck('participant_kind')->all());
        $migration = require database_path('migrations/2026_10_10_180000_add_rpg_npcs.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('rpg_npcs'));
        $this->assertDatabaseHas('rpg_combats', ['id' => $combat->id]);
        $migration->up();
        $this->assertDatabaseHas('rpg_combats', ['id' => $combat->id, 'kind' => 'player_vs_player']);
    }

    public function test_psychic_control_routes_order_to_player_but_execution_to_leader(): void
    {
        $payload = $this->character->payload;
        $payload['attributes']['wi'] = 2;
        $payload['advantages'] = ['Psychische Kraft'];
        $payload['advantage_effects'] = [['name' => 'Psychische Kraft', 'target' => 'Beherrschung']];
        $payload['skills'][] = ['name' => 'Beherrschung', 'value' => 2];
        $this->character->update(['payload' => $payload]);
        $combat = $this->accepted();
        foreach ([1, 2] as $side) {
            $combat = $this->step($combat, 'prepare', $side, ['weapons' => [], 'shield' => false, 'skill' => 'Nahkampf']);
        }
        $this->combatDice->values = [1, 6];
        foreach ([1, 2] as $side) {
            $combat = $this->step($combat, 'initiative', $side);
        }
        $combat = $this->step($combat, 'action', 2, ['kind' => 'psychic', 'power' => 'Beherrschung', 'duration' => 1, 'range' => 1, 'strength' => 0]);
        $this->combatDice->values = [6, 6];
        $combat = $this->step($combat, 'ruling', 0, ['choices' => ['psychic_resistance' => 'triple', 'duration' => 'seconds'], 'reason' => 'Psychische Grundregeln']);
        $this->combatDice->values = [1, 3];
        $combat = $this->step($combat, 'psychic_resistance', 1);
        $order = $combat->decisions()->where('status', 'pending')->where('type', 'npc_order')->sole();
        $this->assertSame('automatic', $order->timeout_policy);
        $this->assertDatabaseHas('rpg_combat_deliveries', ['rpg_combat_decision_id' => $order->id, 'recipient_id' => $this->player->id]);
        $combat = app(CombatService::class)->decide($this->player, $combat->id, $order->id, ['description' => 'Warte auf meine Rückkehr.']);
        $execution = $combat->decisions()->where('status', 'pending')->where('type', 'action')->where('side', 1)->sole();
        $this->assertSame('manual_leader', $execution->timeout_policy);
        $this->actingAs($this->player)->postJson(route('rpg.combats.decide', [$combat, $execution->id]), ['kind' => 'wait'])->assertForbidden();
        $combat = app(CombatService::class)->decide($this->leader, $combat->id, $execution->id, ['kind' => 'wait']);
        $this->assertSame('resolved', $execution->fresh()->status);
    }
}
