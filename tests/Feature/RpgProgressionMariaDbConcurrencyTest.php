<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\RpgAdvancementRequest;
use App\Models\RpgExperienceEntry;
use App\Services\RpgCharacterAdvancementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\RpgProgressionFixtures;
use Tests\TestCase;

class RpgProgressionMariaDbConcurrencyTest extends TestCase
{
    use RefreshDatabase, RpgProgressionFixtures;

    private bool $committed = false;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Separate MariaDB-Konfiguration erforderlich.');
        }
        if (getenv('DB_DATABASE') !== 'omxfc_rpg_test') {
            throw new RuntimeException('Only the isolated omxfc_rpg_test database may be used.');
        }
        parent::setUp();
        $this->progressionFixtures();
    }

    public function test_simultaneous_retries_credit_exactly_once(): void
    {
        $input = ['action' => 'award', 'actor' => $this->leader->id, 'input' => $this->awardInput()];
        $results = $this->parallel($input, $input);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(60, $this->character->experienceBalance());
        $this->assertSame(1, RpgExperienceEntry::count());
        $this->assertSame(1, Activity::where('action', Activity::ACTION_RPG_EXPERIENCE_AWARDED)->count());
    }

    public function test_simultaneous_submissions_allow_only_one_pending_request(): void
    {
        $this->credit();
        $common = ['action' => 'submit', 'actor' => $this->player->id, 'character' => $this->character->id];
        $results = $this->parallel($common + ['input' => $this->advancementInput()], $common + ['input' => $this->advancementInput()]);
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result['ok'])));
        $this->assertSame(1, RpgAdvancementRequest::where('status', 'pending')->count());
        $this->assertSame(60, $this->character->experienceBalance());
    }

    public function test_simultaneous_approvals_debit_and_update_exactly_once(): void
    {
        $this->credit();
        $request = app(RpgCharacterAdvancementService::class)->submit($this->player, $this->character->id, $this->advancementInput());
        $input = ['action' => 'approved', 'actor' => $this->leader->id, 'request' => $request->id];
        $results = $this->parallel($input, $input);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame(54, $this->character->experienceBalance());
        $this->assertSame(1, $this->character->fresh()->revision);
        $this->assertSame(1, RpgExperienceEntry::where('amount', '<', 0)->count());
    }

    public function test_approval_and_withdrawal_have_one_terminal_outcome(): void
    {
        $this->credit();
        $request = app(RpgCharacterAdvancementService::class)->submit($this->player, $this->character->id, $this->advancementInput());
        $results = $this->parallel(
            ['action' => 'approved', 'actor' => $this->leader->id, 'request' => $request->id],
            ['action' => 'withdrawn', 'actor' => $this->player->id, 'request' => $request->id],
        );
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result['ok'])));
        $approved = $request->fresh()->status === 'approved';
        $this->assertSame($approved ? 54 : 60, $this->character->experienceBalance());
        $this->assertSame($approved ? 1 : 0, $this->character->fresh()->revision);
    }

    public function test_deletion_racing_with_approval_leaves_no_orphaned_character_bookings(): void
    {
        $this->credit();
        $request = app(RpgCharacterAdvancementService::class)->submit($this->player, $this->character->id, $this->advancementInput());
        $results = $this->parallel(
            ['action' => 'approved', 'actor' => $this->leader->id, 'request' => $request->id],
            ['action' => 'delete', 'actor' => $this->player->id, 'character' => $this->character->id],
        );
        $this->assertTrue($results[1]['ok']);
        $this->assertNull($this->character->fresh());
        $this->assertDatabaseCount('rpg_experience_entries', 0);
        $this->assertDatabaseCount('rpg_advancement_requests', 0);
        $this->assertDatabaseCount('rpg_experience_awards', 0);
    }

    private function parallel(array $first, array $second): array
    {
        $this->assertSame(1, DB::transactionLevel());
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
