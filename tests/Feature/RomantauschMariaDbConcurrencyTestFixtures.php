<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Enums\Role;
use App\Models\BookOffer;
use App\Models\BookRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\CreatesUserWithRole;

trait RomantauschMariaDbConcurrencyTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private bool $committed = false;

    private function sources(bool $reciprocal = false): array
    {
        $first = $this->createUserWithRole(Role::Mitglied);
        $second = $this->createUserWithRole(Role::Mitglied);
        $data = ['series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman', 'condition' => 'Z0'];
        $offer = BookOffer::create($data + ['user_id' => $first->id]);
        $request = BookRequest::create($data + ['user_id' => $second->id]);
        if ($reciprocal) {
            $data['book_number'] = 2;
            BookOffer::create($data + ['user_id' => $second->id]);
            BookRequest::create($data + ['user_id' => $first->id]);
        }

        return [$offer, $request];
    }

    private function race(array $commands): array
    {
        DB::connection()->commit();
        $this->committed = true;
        $workers = [];
        try {
            foreach ($commands as $command) {
                $process = proc_open([PHP_BINARY, base_path('tests/Support/MariaDbRomantauschWorker.php'), ...array_map(strval(...), $command)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                if (! is_resource($process)) {
                    throw new RuntimeException('Worker konnte nicht starten.');
                }
                stream_set_timeout($pipes[1], 30);
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [$process, $pipes]) {
                expect(fgets($pipes[1]))->toBe("ready\n");
            }
            foreach ($workers as [$process, $pipes]) {
                fwrite($pipes[0], "RUN\n");
                fflush($pipes[0]);
            }
            $results = [];
            foreach ($workers as [$process, $pipes]) {
                $line = fgets($pipes[1]);
                if ($line === false) {
                    $this->fail('Worker fehlgeschlagen: '.stream_get_contents($pipes[2]));
                }
                $results[] = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($workers as [$process, $pipes]) {
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                if (proc_get_status($process)['running']) {
                    proc_terminate($process);
                }
                proc_close($process);
            }
        }
    }
}
