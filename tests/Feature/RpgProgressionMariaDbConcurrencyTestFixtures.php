<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\RpgProgressionFixtures;

trait RpgProgressionMariaDbConcurrencyTestFixtures
{
    use RefreshDatabase, RpgProgressionFixtures;

    private bool $committed = false;

    private function parallel(array $first, array $second): array
    {
        expect(DB::transactionLevel())->toBe(1);
        DB::connection()->commit();
        $this->committed = true;
        $workers = [];
        try {
            foreach ([$first, $second] as $input) {
                $process = proc_open([PHP_BINARY, base_path('tests/Support/MariaDbRpgProgressionWorker.php')], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                if (! is_resource($process)) {
                    throw new RuntimeException('Worker could not start.');
                }
                stream_set_timeout($pipes[1], 30);
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
