<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgCharacter;
use App\Models\RpgCombatDelivery;
use App\Services\RpgCombat\CombatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgCombatMariaDbConcurrencyTestFixtures::class);

beforeEach(function (): void {
    if (getenv('DB_CONNECTION') !== 'mysql') {
        $this->markTestSkipped('Separate MariaDB test configuration required.');
    }
    if (getenv('DB_DATABASE') !== 'omxfc_rpg_test') {
        throw new \RuntimeException('Only isolated omxfc_rpg_test is permitted.');
    }
    $this->combatFixtures();
});

afterEach(function (): void {
    try {
        if ($this->committed) {
            $this->artisan('migrate:fresh')->assertExitCode(0);
        }
    } finally {
    }
});

test('duplicate actions spend and roll once', function (): void {
    $combat = $this->activeCombat();
    $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
    $request = ['action' => 'decide', 'actor' => $this->player->id, 'combat' => $combat->id, 'decision' => $decision->id, 'input' => ['kind' => 'attack', 'weapon' => 'faustschlag-tritt:1']];
    $results = $this->parallel($request, $request);
    expect($results[0]['ok'])->toBeTrue()
        ->and($results[1]['ok'])->toBeTrue()
        ->and($combat->events()->where('kind', 'roll')->count())->toBe(3)
        ->and($combat->decisions()->where('status', 'pending')->count())->toBe(1)
        ->and($combat->decisions()->where('status', 'pending')->first()->type)->toBe('defense');
});

test('competing acceptances reserve character once', function (): void {
    $first = $this->combat(false);
    $own = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $this->progressionPayload()])->refresh();
    $second = app(CombatService::class)->challenge($this->player, $this->combatInput(['character_id' => $own->id]));
    $common = ['action' => 'command', 'actor' => $this->opponent->id, 'command' => 'accept'];
    $results = $this->parallel($common + ['combat' => $first->id, 'key' => (string) Str::uuid()], $common + ['combat' => $second->id, 'key' => (string) Str::uuid()]);
    expect(array_filter($results, fn ($r) => $r['ok']))->toHaveCount(1);
    $this->assertDatabaseCount('rpg_combat_character_locks', 2);
});

test('timeout and late player cannot duplicate a roll', function (): void {
    $combat = $this->activeCombat();
    $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
    $decision->update(['due_at' => now()->subHour()]);
    $common = ['action' => 'decide', 'combat' => $combat->id, 'decision' => $decision->id];
    $results = $this->parallel($common, $common + ['actor' => $this->player->id, 'input' => ['kind' => 'wait']]);
    expect($results[0]['ok'])->toBeTrue()
        ->and($results[1]['ok'])->toBeTrue()
        ->and($decision->fresh()->automatic)->toBeTrue()
        ->and($combat->events()->where('kind', 'roll')->count())->toBe(3);
});

test('deletion and action leave no open orphan', function (): void {
    $combat = $this->activeCombat();
    $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
    $results = $this->parallel(['action' => 'delete', 'character' => $this->character->id], ['action' => 'decide', 'actor' => $this->player->id, 'combat' => $combat->id, 'decision' => $decision->id, 'input' => ['kind' => 'attack', 'weapon' => 'faustschlag-tritt:1']]);
    expect($results[0]['ok'])->toBeTrue()
        ->and($combat->fresh()->status)->toBe('completed');
    $this->assertDatabaseCount('rpg_combat_character_locks', 0);
    expect($combat->decisions()->whereIn('status', ['pending', 'paused'])->count())->toBe(0);
});

test('competing acceptances reserve npc once', function (): void {
    $first = $this->npcInvitation($this->character->id);
    $second = $this->npcInvitation($this->otherCharacter->id);
    $results = $this->parallel(['action' => 'command', 'actor' => $this->player->id, 'combat' => $first->id, 'command' => 'accept', 'key' => (string) Str::uuid()], ['action' => 'command', 'actor' => $this->opponent->id, 'combat' => $second->id, 'command' => 'accept', 'key' => (string) Str::uuid()]);
    expect(array_filter($results, fn ($r) => $r['ok']))->toHaveCount(1);
    $this->assertDatabaseCount('rpg_combat_npc_locks', 1);
    $this->assertDatabaseCount('rpg_combat_character_locks', 1);
});

test('player and npc invitations cannot reserve same player', function (): void {
    $first = $this->combat(false);
    $second = $this->npcInvitation($this->otherCharacter->id);
    $base = ['action' => 'command', 'actor' => $this->opponent->id, 'command' => 'accept'];
    $results = $this->parallel($base + ['combat' => $first->id, 'key' => (string) Str::uuid()], $base + ['combat' => $second->id, 'key' => (string) Str::uuid()]);
    expect(array_filter($results, fn ($r) => $r['ok']))->toHaveCount(1)
        ->and(DB::table('rpg_combat_character_locks')->where('rpg_character_id', $this->otherCharacter->id)->count())->toBe(1);
});

test('simultaneous special creations enforce uniqueness', function (): void {
    $base = ['action' => 'npc-create', 'actor' => $this->leader->id];
    $results = $this->parallel($base + ['input' => ['template_key' => 'maddrax', 'submission_key' => (string) Str::uuid()]], $base + ['input' => ['template_key' => 'maddrax', 'submission_key' => (string) Str::uuid()]]);
    expect(array_filter($results, fn ($r) => $r['ok']))->toHaveCount(1);
    $this->assertDatabaseCount('rpg_npcs', 1);
});

test('duplicate reminders create one delivery without resolving decision', function (): void {
    $combat = app(CombatService::class)->command($this->player, $this->npcInvitation($this->character->id)->id, 'accept', (string) Str::uuid());
    $decision = $combat->decisions()->where('side', 1)->sole();
    $decision->update(['reminder_at' => now('UTC')->subMinute()]);
    $request = ['action' => 'remind', 'combat' => $combat->id, 'decision' => $decision->id];
    $results = $this->parallel($request, $request);
    expect($results[0]['ok'])->toBeTrue()
        ->and($results[1]['ok'])->toBeTrue()
        ->and(RpgCombatDelivery::where('kind', 'reminder')->count())->toBe(1)
        ->and($decision->fresh()->status)->toBe('pending');
});

test('identity triggers reject invalid inserts and updates', function (): void {
    expect((int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled)->toBe(1);
    $combat = $this->npcInvitation($this->character->id);
    $participants = $combat->participants->keyBy('participant_kind');
    foreach ([['npc', ['rpg_character_id' => $this->character->id]], ['npc', ['owner_id' => $this->leader->id]], ['player', ['rpg_npc_id' => $participants['npc']->rpg_npc_id]], ['player', ['participant_kind' => 'unknown']]] as [$kind, $changes]) {
        $participant = $participants[$kind];
        $row = (array) DB::table('rpg_combat_participants')->find($participant->id);
        unset($row['id']);
        $this->assertInvalidIdentity(fn () => DB::table('rpg_combat_participants')->insert(array_replace($row, ['side' => 3], $changes)));
        $this->assertInvalidIdentity(fn () => DB::table('rpg_combat_participants')->where('id', $participant->id)->update($changes));
    }
    $this->assertDatabaseCount('rpg_combat_participants', 2);
});

test('npc migration round trip preserves pvp and foreign key deletions', function (): void {
    expect((int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled)->toBe(1);
    $combat = $this->combat(false);
    // MariaDB DDL commits implicitly; leave RefreshDatabase's transaction first.
    DB::connection()->commit();
    $this->committed = true;
    $migration = require database_path('migrations/2026_10_10_180000_add_rpg_npcs.php');
    $migration->down();
    expect(Schema::hasTable('rpg_npcs'))->toBeFalse();
    $this->assertDatabaseHas('rpg_combats', ['id' => $combat->id]);
    expect(DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::getDatabaseName())->where('TRIGGER_NAME', 'like', 'rpg_participant_identity_%')->count())->toBe(0);
    $migration->up();
    $this->assertDatabaseHas('rpg_combats', ['id' => $combat->id, 'kind' => 'player_vs_player']);
    expect($combat->fresh()->participants->pluck('participant_kind')->all())->toBe(['player', 'player'])
        ->and(DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::getDatabaseName())->where('TRIGGER_NAME', 'like', 'rpg_participant_identity_%')->count())->toBe(2);
    $npcCombat = $this->npcInvitation($this->otherCharacter->id);
    $npcParticipant = $npcCombat->participants->where('participant_kind', 'npc')->sole();
    DB::table('rpg_npcs')->where('id', $npcParticipant->rpg_npc_id)->delete();
    expect($npcParticipant->fresh()->rpg_npc_id)->toBeNull()
        ->and($npcParticipant->fresh()->snapshot)->toBe($npcParticipant->snapshot);
    DB::table('rpg_characters')->where('id', $this->character->id)->delete();
    expect($combat->participants->where('side', 1)->sole()->fresh()->rpg_character_id)->toBeNull()
        ->and((int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled)->toBe(1);
});
