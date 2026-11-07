<?php

namespace Tests\Feature;

use App\Models\RpgCharacter;
use App\Services\RpgCharacterAdvancementService;
use App\Services\RpgInventoryAdapter;
use App\Services\RpgInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\RpgInventoryFixtures;
use Tests\TestCase;

class RpgInventoryMariaDbConcurrencyTest extends TestCase
{
    use RefreshDatabase, RpgInventoryFixtures;

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
        $this->inventoryInitialize();
    }

    public function test_simultaneous_retries_book_money_and_goods_exactly_once(): void
    {
        $action = $this->action($this->character, 'grant', ['amount' => 5, 'incoming' => [$this->position('item:seil')]]);
        $results = $this->parallel($action, $action);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(105, $this->character->fresh()->payload['equipment']['bucks_balance']);
        $this->assertDatabaseCount('rpg_inventory_transactions', 2);
        $this->assertDatabaseCount('rpg_inventory_entries', 2);
    }

    public function test_competing_purchases_cannot_overspend_or_duplicate_goods(): void
    {
        $changes = ['amount' => 70, 'incoming' => [$this->position('item:bogen')], 'external_party' => 'Händler', 'price_reason' => 'Knappes Angebot'];
        $results = $this->parallel($this->action($this->character, 'purchase', $changes), $this->action($this->character, 'purchase', $changes));
        $this->assertSame(1, count(array_filter($results, fn ($row) => $row['ok'])));
        $state = app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload);
        $this->assertSame(30, $state['bucks']);
        $this->assertSame(1, $state['stocks']['item:bogen']);
    }

    public function test_opposing_character_trades_preserve_totals_without_deadlocks(): void
    {
        $other = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $this->progressionPayload()]);
        $this->inventoryInitialize(100, [$this->position('item:bogen', 2)], $other);
        $this->inventoryBook('grant', ['incoming' => [$this->position('item:seil', 2)]]);
        $a = $this->action($this->character, 'swap', ['incoming' => [$this->position('item:bogen')], 'outgoing' => [$this->position('item:seil')],
            'amount' => 10, 'cash_direction' => 'expense', 'other_character_id' => $other->id, 'other_revision' => $other->fresh()->revision]);
        $b = $this->action($other, 'swap', ['incoming' => [$this->position('item:seil')], 'outgoing' => [$this->position('item:bogen')],
            'amount' => 10, 'other_character_id' => $this->character->id, 'other_revision' => $this->character->fresh()->revision]);
        $results = $this->parallel($a, $b);
        $this->assertSame(1, count(array_filter($results, fn ($row) => $row['ok'])));
        $first = app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload);
        $second = app(RpgInventoryAdapter::class)->state($other->fresh()->payload);
        $this->assertSame(200, $first['bucks'] + $second['bucks']);
        $this->assertSame(2, $first['stocks']['item:seil'] + $second['stocks']['item:seil']);
        $this->assertSame(2, $first['stocks']['item:bogen'] + $second['stocks']['item:bogen']);
    }

    public function test_inventory_and_advancement_approval_never_overwrite_each_other(): void
    {
        $this->credit();
        $request = app(RpgCharacterAdvancementService::class)->submit($this->player, $this->character->id, $this->advancementInput());
        $results = $this->parallel($this->action($this->character, 'grant', ['amount' => 5]),
            ['action' => 'approved', 'actor' => $this->leader->id, 'request' => $request->id]);
        $this->assertSame(1, count(array_filter($results, fn ($row) => $row['ok'])));
        $approved = $request->fresh()->status === 'approved';
        $this->assertSame($approved ? 54 : 60, $this->character->experienceBalance());
        $this->assertSame($approved ? 100 : 105, $this->character->fresh()->payload['equipment']['bucks_balance']);
        $this->assertContains($request->fresh()->status, ['approved', 'superseded']);
    }

    public function test_character_deletion_racing_with_booking_leaves_no_private_entries(): void
    {
        $results = $this->parallel($this->action($this->character, 'grant', ['amount' => 5]),
            ['action' => 'delete', 'actor' => $this->player->id, 'character' => $this->character->id]);
        $this->assertTrue($results[1]['ok']);
        $this->assertNull($this->character->fresh());
        $this->assertDatabaseCount('rpg_inventory_entries', 0);
    }

    private function action(RpgCharacter $character, string $kind, array $changes): array
    {
        $input = $this->inventoryInput($kind, $changes, $character);
        $input['preview_hash'] = app(RpgInventoryService::class)->preview($this->leader, $character->id, $input)['preview_hash'];

        return ['action' => 'inventory', 'actor' => $this->leader->id, 'character' => $character->id, 'input' => $input];
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
