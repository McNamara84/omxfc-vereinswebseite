<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\BaxxEarningRule;
use App\Models\Book;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\UserPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class RomantauschMariaDbConcurrencyTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private bool $committed = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Benötigt echte MariaDB-Verbindungen.');
        }
        if (DB::connection()->getDatabaseName() !== 'omxfc_maddraxikon_test') {
            throw new RuntimeException('Unsichere Testdatenbank verweigert.');
        }
        foreach (range(1, 3) as $number) {
            Book::create(['roman_number' => $number, 'title' => "Roman {$number}", 'author' => 'Autor', 'type' => BookType::MaddraxDieDunkleZukunftDerErde]);
        }
        BaxxEarningRule::whereIn('action_key', ['romantausch_offer', 'romantausch_swap_complete'])->update(['points' => 3, 'every_count' => 1, 'is_active' => true]);
    }

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
                $process = proc_open([PHP_BINARY, base_path('tests/Support/MariaDbRomantauschWorker.php'), ...array_map(strval(...), $command)],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                if (! is_resource($process)) {
                    throw new RuntimeException('Worker konnte nicht starten.');
                }
                stream_set_timeout($pipes[1], 30);
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [$process, $pipes]) {
                $this->assertSame("ready\n", fgets($pipes[1]), stream_get_meta_data($pipes[1])['timed_out'] ? 'Worker-Timeout' : 'Worker nicht bereit');
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

    public function test_parallel_creations_cross_a_threshold_only_once(): void
    {
        [$offer] = $this->sources();
        BaxxEarningRule::where('action_key', 'romantausch_offer')->update(['every_count' => 2]);
        $this->race([['create', $offer->user_id], ['create', $offer->user_id]]);
        $this->assertSame(3, BookOffer::where('user_id', $offer->user_id)->count());
        $this->assertSame(1, UserPoint::count());
        $this->assertSame(3, (int) UserPoint::sum('points'));
    }

    public function test_parallel_matchers_create_one_reciprocal_pair_and_two_notifications(): void
    {
        [$offer] = $this->sources(true);
        $results = $this->race([['match', $offer->user_id, $offer->id], ['match', $offer->user_id, $offer->id]]);
        $this->assertDatabaseCount('book_swaps', 2);
        $this->assertSame(2, array_sum(array_column($results, 'mails')));
        $this->assertSame(2, BookSwap::distinct()->count('offer_id'));
        $this->assertSame(2, BookSwap::distinct()->count('request_id'));
    }

    public function test_parallel_confirmations_award_each_participant_once(): void
    {
        [$offer, $request] = $this->sources();
        $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
        $this->race([['confirm', $offer->user_id, $swap->id], ['confirm', $request->user_id, $swap->id]]);
        $this->assertNotNull($swap->fresh()->completed_at);
        $this->assertSame(2, UserPoint::count());
        $this->assertSame(6, (int) UserPoint::sum('points'));
        $this->assertSame(1, Activity::where('action', 'swap_completed')->count());
    }

    public function test_confirmation_against_manual_completion_is_idempotent(): void
    {
        $manager = $this->createUserWithRole(Role::Vorstand);
        [$offer, $request] = $this->sources();
        $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id, 'offer_confirmed' => true]);
        $this->race([['confirm', $request->user_id, $swap->id], ['complete', $manager->id, $offer->id, $request->id]]);
        $this->assertDatabaseCount('book_swaps', 1);
        $this->assertSame(2, UserPoint::count());
        $this->assertSame(1, Activity::where('action', 'swap_completed')->count());
    }

    public static function sourceRaces(): array
    {
        return [['update'], ['delete']];
    }

    #[DataProvider('sourceRaces')]
    public function test_matching_against_source_mutation_remains_consistent(string $mutation): void
    {
        [$offer, $request] = $this->sources(true);
        $this->race([['match', $offer->user_id, $offer->id], [$mutation, $offer->user_id, $offer->id]]);
        $this->assertSame(0, BookSwap::whereDoesntHave('offer')->orWhereDoesntHave('request')->count());
        $this->assertSame(BookSwap::count(), BookSwap::distinct()->count('offer_id'));
        if ($mutation === 'delete') {
            $this->assertNull($offer->fresh());
            $this->assertFalse(BookSwap::where('offer_id', $offer->id)->exists());
        }
    }

    public function test_bundle_change_against_matching_rechecks_current_state(): void
    {
        [$offer] = $this->sources(true);
        $offer->update(['bundle_id' => 'parallel-bundle']);
        BookOffer::create(['user_id' => $offer->user_id, 'bundle_id' => 'parallel-bundle', 'series' => $offer->series, 'book_number' => 3, 'book_title' => 'Roman 3', 'condition' => 'Z0']);
        $this->race([['match', $offer->user_id, $offer->id], ['bundle-update', $offer->user_id, 'parallel-bundle']]);
        $this->assertSame(BookSwap::count(), BookSwap::distinct()->count('offer_id'));
        $this->assertSame(0, BookSwap::whereDoesntHave('offer')->orWhereDoesntHave('request')->count());
    }

    public function test_bundle_change_against_deletion_never_recreates_deleted_sources(): void
    {
        [$offer] = $this->sources();
        $offer->update(['bundle_id' => 'parallel-bundle']);
        BookOffer::create(['user_id' => $offer->user_id, 'bundle_id' => 'parallel-bundle', 'series' => $offer->series, 'book_number' => 2, 'book_title' => 'Roman 2', 'condition' => 'Z0']);
        $this->race([['bundle-update', $offer->user_id, 'parallel-bundle'], ['bundle-delete', $offer->user_id, 'parallel-bundle']]);
        $this->assertSame(0, BookOffer::where('bundle_id', 'parallel-bundle')->count());
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
