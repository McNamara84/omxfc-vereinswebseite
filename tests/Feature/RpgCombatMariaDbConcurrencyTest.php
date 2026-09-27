<?php

namespace Tests\Feature;

use App\Models\RpgCharacter;
use App\Services\RpgCombat\CombatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RpgCombatFixtures;
use Tests\TestCase;

class RpgCombatMariaDbConcurrencyTest extends TestCase
{
    use RefreshDatabase,RpgCombatFixtures;

    private bool $committed = false;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Separate MariaDB test configuration required.');
        }
        if (getenv('DB_DATABASE') !== 'omxfc_rpg_test') {
            throw new \RuntimeException('Only isolated omxfc_rpg_test is permitted.');
        }
        parent::setUp();
        $this->combatFixtures();
    }

    public function test_duplicate_actions_spend_and_roll_once(): void
    {
        $combat = $this->activeCombat();
        $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
        $request = ['action' => 'decide', 'actor' => $this->player->id, 'combat' => $combat->id, 'decision' => $decision->id, 'input' => ['kind' => 'attack', 'weapon' => 'faustschlag-tritt:1']];
        $results = $this->parallel($request, $request);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame(3, $combat->events()->where('kind', 'roll')->count());
        $this->assertSame(1, $combat->decisions()->where('status', 'pending')->count());
        $this->assertSame('defense', $combat->decisions()->where('status', 'pending')->first()->type);
    }

    public function test_competing_acceptances_reserve_character_once(): void
    {
        $first = $this->combat(false);
        $own = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $this->progressionPayload()])->refresh();
        $second = app(CombatService::class)->challenge($this->player, $this->combatInput(['character_id' => $own->id]));
        $common = ['action' => 'command', 'actor' => $this->opponent->id, 'command' => 'accept'];
        $results = $this->parallel($common + ['combat' => $first->id, 'key' => (string) Str::uuid()], $common + ['combat' => $second->id, 'key' => (string) Str::uuid()]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertDatabaseCount('rpg_combat_character_locks', 2);
    }

    public function test_timeout_and_late_player_cannot_duplicate_a_roll(): void
    {
        $combat = $this->activeCombat();
        $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
        $decision->update(['due_at' => now()->subHour()]);
        $common = ['action' => 'decide', 'combat' => $combat->id, 'decision' => $decision->id];
        $results = $this->parallel($common, $common + ['actor' => $this->player->id, 'input' => ['kind' => 'wait']]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertTrue($decision->fresh()->automatic);
        $this->assertSame(3, $combat->events()->where('kind', 'roll')->count());
    }

    public function test_deletion_and_action_leave_no_open_orphan(): void
    {
        $combat = $this->activeCombat();
        $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
        $results = $this->parallel(['action' => 'delete', 'character' => $this->character->id],
            ['action' => 'decide', 'actor' => $this->player->id, 'combat' => $combat->id, 'decision' => $decision->id, 'input' => ['kind' => 'attack', 'weapon' => 'faustschlag-tritt:1']]);
        $this->assertTrue($results[0]['ok']);
        $this->assertSame('completed', $combat->fresh()->status);
        $this->assertDatabaseCount('rpg_combat_character_locks', 0);
        $this->assertSame(0, $combat->decisions()->whereIn('status', ['pending', 'paused'])->count());
    }

    private function parallel(array $first, array $second): array
    {
        $this->assertSame(1, DB::transactionLevel());
        DB::connection()->commit();
        $this->committed = true;
        $workers = [];
        try {
            foreach ([$first, $second] as $input) {
                $process = proc_open([PHP_BINARY, base_path('tests/Support/MariaDbRpgCombatWorker.php')], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                if (! is_resource($process)) {
                    throw new \RuntimeException('Worker could not start.');
                }
                stream_set_timeout($pipes[1], 60);
                $workers[] = compact('process', 'pipes', 'input');
            }
            foreach ($workers as $worker) {
                $this->assertSame("ready\n", fgets($worker['pipes'][1]));
            }
            foreach ($workers as $worker) {
                fwrite($worker['pipes'][0], json_encode($worker['input'], JSON_THROW_ON_ERROR)."\n");
                fflush($worker['pipes'][0]);
            }
            $results = [];
            foreach ($workers as $worker) {
                $line = fgets($worker['pipes'][1]);
                $this->assertNotFalse($line, stream_get_contents($worker['pipes'][2]));
                $results[] = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($workers as $worker) {
                foreach ($worker['pipes'] as $pipe) {
                    fclose($pipe);
                }
                if (proc_get_status($worker['process'])['running']) {
                    proc_terminate($worker['process']);
                }
                proc_close($worker['process']);
            }
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->committed) {
                $this->artisan('migrate:fresh')->assertExitCode(0);
            }
        } finally {
            parent::tearDown();
        }
    }
}
