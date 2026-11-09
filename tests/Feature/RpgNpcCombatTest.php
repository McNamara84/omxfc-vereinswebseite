<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendRpgCombatMail;
use App\Mail\RpgCombatMail;
use App\Models\RpgCombatDelivery;
use App\Models\RpgCombatMilestone;
use App\Services\RpgCombat\CombatQuery;
use App\Services\RpgCombat\CombatService;
use App\Services\RpgNpcService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgNpcCombatTestFixtures::class);
dataset('RpgNpcCombatTest.cancellationCommands', static fn (): array => ['decline' => ['decline', 2, 'declined'], 'withdraw' => ['withdraw', 1, 'withdrawn']]);
beforeEach(function (): void {
    $this->combatFixtures();
    Mail::fake();
    $this->npc = app(RpgNpcService::class)->create($this->leader, ['submission_key' => (string) Str::uuid(), 'template_key' => 'androne']);
});
test('native npc form uses only the selected participant revisions', function (): void {
    $input = ['kind' => 'npc_vs_player', 'npc_id' => $this->npc->id, 'opponent_id' => $this->character->id, 'npc_revisions' => [$this->npc->id => $this->npc->revision, 99999 => 7], 'opponent_revisions' => [$this->character->id => $this->character->revision, 99999 => 8], 'distance' => 1, 'submission_key' => (string) Str::uuid()];
    $this->actingAs($this->leader)->get(route('rpg.combats.create', ['npc_id' => $this->npc->id]))->assertOk()->assertSee('name="npc_revisions['.$this->npc->id.']"', false)->assertSee('name="opponent_revisions['.$this->character->id.']"', false);
    $this->post(route('rpg.combats.store'), $input)->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseCount('rpg_combats', 1);
});
test('invitation and acceptance have npc snapshots locks dashboard and notifications', function (): void {
    $key = (string) Str::uuid();
    $combat = $this->invitation(['submission_key' => $key]);
    expect($this->invitation(['submission_key' => $key])->id)->toBe($combat->id)
        ->and($combat->kind)->toBe('npc_vs_player')
        ->and($combat->participants[0]->participant_kind)->toBe('npc')
        ->and($combat->participants[0]->owner_id)->toBeNull();
    $this->assertDatabaseCount('rpg_combat_npc_locks', 0);
    expect(RpgCombatMilestone::firstOrFail()->message())->toContain('Androne fordert');
    $this->actingAs($this->leader)->get(route('rpg.combats.create', ['npc_id' => $this->npc->id]))->assertOk()->assertSee('NSC herausfordern');
    $this->actingAs($this->player)->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('Ablehnen');
    $combat = app(CombatService::class)->command($this->player, $combat->id, 'accept', (string) Str::uuid());
    $this->assertDatabaseCount('rpg_combat_npc_locks', 1);
    $this->assertDatabaseCount('rpg_combat_character_locks', 1);
    $npcDecision = $combat->decisions()->where('side', 1)->sole();
    expect($npcDecision->timeout_policy)->toBe('manual_leader')
        ->and($npcDecision->due_at)->toBeNull()
        ->and($npcDecision->reminder_at)->not->toBeNull();
    $html = $this->actingAs($this->leader)->getJson(route('rpg.combats.show', $combat))->assertOk()->json('html');
    expect($html)->toContain('persönlich')
        ->and(app(CombatQuery::class)->hints($this->leader))->toHaveCount(1)
        ->and($this->character->fresh()->payload)->toBe($this->progressionPayload());
    foreach (RpgCombatDelivery::all() as $delivery) {
        (new SendRpgCombatMail($delivery->id))->handle();
    }
    Mail::assertSent(RpgCombatMail::class, fn ($mail) => $mail->hasTo($this->leader->email));
    Mail::assertSent(RpgCombatMail::class, fn ($mail) => $mail->hasTo($this->player->email));
});
test('unaccepted invitation can be closed without effects', function (string $command, int $side, string $status): void {
    $combat = $this->invitation();
    $user = $side === 1 ? $this->leader : $this->player;
    $key = (string) Str::uuid();
    app(CombatService::class)->command($user, $combat->id, $command, $key);
    app(CombatService::class)->command($user, $combat->id, $command, $key);
    expect($combat->fresh()->status)->toBe($status);
    $this->assertDatabaseCount('rpg_combat_npc_locks', 0);
    expect($this->combatDice->calls)->toBe(0);
    $this->assertDatabaseCount('rpg_combat_milestones', 1);
})->with('RpgNpcCombatTest.cancellationCommands');
test('complete combat and retries never roll twice or change originals', function (): void {
    $combat = $this->active();
    $profile = $this->npc->profile;
    $this->combatDice->values = [6, 6, 1, 1, 6];
    $decision = $combat->decisions()->where('status', 'pending')->sole();
    $input = ['kind' => 'attack', 'weapon' => 'body:0'];
    $combat = app(CombatService::class)->decide($this->leader, $combat->id, $decision->id, $input);
    $calls = $this->combatDice->calls;
    app(CombatService::class)->decide($this->leader, $combat->id, $decision->id, $input);
    expect($this->combatDice->calls)->toBe($calls);
    $combat = $this->step($combat, 'defense', 2, ['defense' => 'dodge']);
    $combat = $this->step($combat, 'damage', 1);
    expect($combat->status)->toBe('completed')
        ->and($combat->winner_side)->toBe(1);
    $this->assertDatabaseCount('rpg_combat_npc_locks', 0);
    $this->assertDatabaseCount('rpg_combat_character_locks', 0);
    expect($this->npc->fresh()->profile)->toBe($profile)
        ->and($this->character->fresh()->payload)->toBe($this->progressionPayload())
        ->and($this->character->experienceBalance())->toBe(0)
        ->and(RpgCombatMilestone::count())->toBe(3);
});
test('npc and sl inputs never timeout but remind once and accept late input', function (): void {
    $combat = $this->accepted();
    $npcDecision = $combat->decisions()->where('side', 1)->sole();
    $playerDecision = $combat->decisions()->where('side', 2)->sole();
    $this->travel(25)->hours();
    app(CombatService::class)->decide(null, $combat->id, $npcDecision->id);
    expect($npcDecision->fresh()->status)->toBe('pending');
    $this->artisan('rpg:process-combats')->assertSuccessful();
    $this->artisan('rpg:process-combats')->assertSuccessful();
    expect($playerDecision->fresh()->automatic)->toBeTrue()
        ->and($npcDecision->fresh()->status)->toBe('pending')
        ->and($npcDecision->fresh()->automatic)->toBeFalse()
        ->and(RpgCombatDelivery::where('kind', 'reminder')->count())->toBe(1)
        ->and($npcDecision->fresh()->reminded_at)->not->toBeNull();
    $this->step($combat, 'prepare', 1, ['weapons' => [], 'shield' => false, 'skill' => 'Nahkampf']);
    expect($npcDecision->fresh()->automatic)->toBeFalse();
});
test('manual ruling pauses player deadline and remains open after days', function (): void {
    $this->npc = app(RpgNpcService::class)->create($this->leader, ['submission_key' => (string) Str::uuid(), 'template_key' => 'bandit']);
    $combat = $this->accepted();
    $playerDecision = $combat->decisions()->where('side', 2)->sole();
    $this->travel(3)->hours();
    $combat = $this->step($combat, 'prepare', 1, ['weapons' => ['schwert:1'], 'shield' => false, 'skill' => 'Nahkampf']);
    expect($playerDecision->fresh()->status)->toBe('paused');
    $pausedMail = RpgCombatDelivery::where('rpg_combat_decision_id', $playerDecision->id)->sole();
    (new SendRpgCombatMail($pausedMail->id))->handle();
    expect($pausedMail->fresh()->status)->toBe('cancelled');
    $remaining = $playerDecision->fresh()->remaining_seconds;
    $this->travel(3)->days();
    $this->artisan('rpg:process-combats')->assertSuccessful();
    expect($combat->fresh()->status)->toBe('awaiting_ruling')
        ->and($playerDecision->fresh()->status)->toBe('paused');
    $combat = $this->step($combat, 'ruling', 0, ['choices' => ['hands' => 'catalog'], 'reason' => 'Gedruckte Handbelegung']);
    expect($playerDecision->fresh()->status)->toBe('pending')
        ->and($pausedMail->fresh()->status)->toBe('pending')
        ->and($playerDecision->fresh()->due_at->timestamp)->toBe(now('UTC')->addSeconds($remaining)->timestamp)
        ->and($combat->state['seconds'])->toBe(0);
});
test('handover revokes old authority and cancels stale mail', function (): void {
    $combat = $this->accepted();
    $decision = $combat->decisions()->where('side', 1)->sole();
    $mail = RpgCombatDelivery::where('rpg_combat_decision_id', $decision->id)->sole();
    $reminder = $decision->reminder_at->timestamp;
    $this->rpgTeam->update(['user_id' => $this->opponent->id]);
    expect($combat->fresh()->leader_id)->toBe($this->opponent->id);
    $this->assertDatabaseHas('rpg_combat_deliveries', ['rpg_combat_id' => $combat->id, 'kind' => 'handover', 'recipient_id' => $this->opponent->id]);
    expect($decision->fresh()->reminder_at->timestamp)->toBe($reminder);
    $this->actingAs($this->leader)->postJson(route('rpg.combats.decide', [$combat, $decision->id]), ['weapons' => [], 'shield' => false, 'skill' => 'Nahkampf'])->assertForbidden();
    (new SendRpgCombatMail($mail->id))->handle();
    expect($mail->fresh()->status)->toBe('cancelled');
    $newMail = RpgCombatDelivery::where('rpg_combat_decision_id', $decision->id)->where('recipient_id', $this->opponent->id)->sole();
    (new SendRpgCombatMail($newMail->id))->handle();
    Mail::assertSent(RpgCombatMail::class, fn ($m) => $m->hasTo($this->opponent->email));
    $this->actingAs($this->opponent)->post(route('rpg.combats.decide', [$combat, $decision->id]), ['weapons' => [], 'shield' => '0', 'skill' => 'Nahkampf'])->assertRedirect();
});
test('missing active leader suspends and restores remaining deadlines', function (): void {
    $combat = $this->accepted();
    $this->travel(2)->hours();
    $this->rpgTeam->users()->detach($this->leader);
    app(CombatService::class)->sweep($combat->id);
    expect($combat->fresh()->suspension)->not->toBeNull()
        ->and($combat->decisions()->where('status', 'paused')->count())->toBe(2);
    $player = $combat->decisions()->where('side', 2)->sole();
    $remaining = $player->remaining_seconds;
    $this->travel(2)->days();
    $this->artisan('rpg:process-combats')->assertSuccessful();
    expect($this->combatDice->calls)->toBe(0);
    $this->rpgTeam->update(['user_id' => $this->opponent->id]);
    expect($combat->fresh()->suspension)->toBeNull()
        ->and($player->fresh()->due_at->timestamp)->toBe(now('UTC')->addSeconds($remaining)->timestamp);
});
test('new leader may control both sides of an existing combat', function (): void {
    $combat = $this->accepted();
    $this->rpgTeam->update(['user_id' => $this->player->id]);
    $detail = app(CombatQuery::class)->detail($this->player, $combat->fresh());
    expect($detail['sides'])->toBe([1, 2]);
    $this->actingAs($this->player)->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('name="side" value="1"', false)->assertSee('name="side" value="2"', false);
    $this->post(route('rpg.combats.command', $combat), ['command' => 'abort', 'side' => 1, 'submission_key' => (string) Str::uuid()])->assertRedirect();
    $this->post(route('rpg.combats.command', $combat), ['command' => 'abort', 'side' => 2, 'submission_key' => (string) Str::uuid()])->assertRedirect();
    expect($combat->fresh()->result)->toBe('cancelled');
});
test('busy instance stale snapshots and membership cleanup', function (): void {
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
    expect($combat->fresh()->result)->toBe('membership_changed');
    $this->assertDatabaseCount('rpg_combat_npc_locks', 0);
});
test('snapshot tampering is rejected before invitation', function (): void {
    DB::table('rpg_npcs')->where('id', $this->npc->id)->update(['profile_hash' => str_repeat('0', 64)]);
    expect(fn () => $this->invitation())->toThrow(ValidationException::class);
});
test('only leader can decide npc and only owner can accept', function (): void {
    $combat = $this->invitation();
    $this->actingAs($this->leader)->postJson(route('rpg.combats.command', $combat), ['command' => 'accept', 'submission_key' => (string) Str::uuid()])->assertForbidden();
    $this->actingAs($this->opponent)->get(route('rpg.combats.show', $combat))->assertForbidden();
    $combat = app(CombatService::class)->command($this->player, $combat->id, 'accept', (string) Str::uuid());
    $decision = $combat->decisions()->where('side', 1)->sole();
    $this->actingAs($this->player)->postJson(route('rpg.combats.decide', [$combat, $decision->id]), ['weapons' => [], 'shield' => false, 'skill' => 'Nahkampf'])->assertForbidden();
    $this->actingAs($this->leader)->postJson(route('rpg.combats.decide', [$combat, $decision->id]), ['dice' => [6, 6]])->assertUnprocessable();
    expect($this->combatDice->calls)->toBe(0);
});
test('mixed participant identity is rejected by database', function (): void {
    $combat = $this->invitation();
    expect(fn () => DB::table('rpg_combat_participants')->where('rpg_combat_id', $combat->id)->where('side', 1)->update(['rpg_character_id' => $this->character->id]))->toThrow(QueryException::class);
});
test('database defaults preserve historical pvp and npc migration can be reversed', function (): void {
    $combat = $this->combat(false);
    expect($combat->kind)->toBe('player_vs_player')
        ->and($combat->participants->pluck('participant_kind')->all())->toBe(['player', 'player']);
    $migration = require database_path('migrations/2026_10_10_180000_add_rpg_npcs.php');
    $migration->down();
    expect(Schema::hasTable('rpg_npcs'))->toBeFalse();
    $this->assertDatabaseHas('rpg_combats', ['id' => $combat->id]);
    $migration->up();
    $this->assertDatabaseHas('rpg_combats', ['id' => $combat->id, 'kind' => 'player_vs_player']);
});
test('psychic control routes order to player but execution to leader', function (): void {
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
    expect($order->timeout_policy)->toBe('automatic');
    $this->assertDatabaseHas('rpg_combat_deliveries', ['rpg_combat_decision_id' => $order->id, 'recipient_id' => $this->player->id]);
    $combat = app(CombatService::class)->decide($this->player, $combat->id, $order->id, ['description' => 'Warte auf meine Rückkehr.']);
    $execution = $combat->decisions()->where('status', 'pending')->where('type', 'action')->where('side', 1)->sole();
    expect($execution->timeout_policy)->toBe('manual_leader');
    $this->actingAs($this->player)->postJson(route('rpg.combats.decide', [$combat, $execution->id]), ['kind' => 'wait'])->assertForbidden();
    $combat = app(CombatService::class)->decide($this->leader, $combat->id, $execution->id, ['kind' => 'wait']);
    expect($execution->fresh()->status)->toBe('resolved');
});
