<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgCharacter;
use App\Models\RpgCombat;
use App\Models\RpgNpc;
use App\Services\RpgCombat\CombatService;
use App\Services\RpgNpcService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RpgCombatFixtures;

trait RpgCombatMariaDbConcurrencyTestFixtures
{
    use RefreshDatabase, RpgCombatFixtures;

    private bool $committed = false;

    private function npcInvitation(int $opponent): RpgCombat
    {
        $npc = RpgNpc::first() ?? app(RpgNpcService::class)->create($this->leader, ['template_key' => 'androne', 'submission_key' => (string) Str::uuid()]);
        $character = RpgCharacter::findOrFail($opponent);

        return app(CombatService::class)->challenge($this->leader, ['kind' => 'npc_vs_player', 'submission_key' => (string) Str::uuid(), 'npc_id' => $npc->id, 'npc_revision' => $npc->revision, 'opponent_id' => $character->id, 'opponent_revision' => $character->revision, 'distance' => 1]);
    }

    private function assertInvalidIdentity(callable $write): void
    {
        try {
            $write();
            $this->fail('Invalid participant identity was accepted.');
        } catch (QueryException $exception) {
            expect($exception->errorInfo[0])->toBe('45000')
                ->and($exception->getMessage())->toContain('Invalid RPG participant identity');
        }
    }

    private function parallel(array $first, array $second): array
    {
        expect(DB::transactionLevel())->toBe(1);
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
                expect(fgets($worker['pipes'][1]))->toBe("ready\n");
            }
            foreach ($workers as $worker) {
                fwrite($worker['pipes'][0], json_encode($worker['input'], JSON_THROW_ON_ERROR)."\n");
                fflush($worker['pipes'][0]);
            }
            $results = [];
            foreach ($workers as $worker) {
                $line = fgets($worker['pipes'][1]);
                expect($line)->not->toBeFalse();
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
}
