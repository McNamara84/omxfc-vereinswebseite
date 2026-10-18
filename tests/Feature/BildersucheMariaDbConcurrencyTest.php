<?php

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Services\RewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class BildersucheMariaDbConcurrencyTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private bool $committed = false;

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_parallel_first_purchases_cannot_duplicate_or_overdraw_the_wallet(bool $differentRewards): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Requires MariaDB.');
        }
        if (! app()->environment('testing') || DB::getDatabaseName() !== 'omxfc_maddraxikon_test') {
            throw new RuntimeException('Unsafe test database.');
        }
        config(['bildersuche.enabled' => true]);
        $user = $this->actingMemberWithPoints(100);
        $firstReward = Reward::where('slug', 'bildersuche')->firstOrFail();
        $secondReward = $differentRewards ? Reward::factory()->create(['cost_baxx' => 100]) : $firstReward;
        DB::connection()->commit();
        $this->committed = true;

        $workers = [];
        try {
            foreach ([$firstReward, $secondReward] as $reward) {
                $input = new InputStream;
                $process = new Process([PHP_BINARY, base_path('tests/Support/MariaDbBildersuchePurchaseWorker.php'), (string) $user->id, (string) $reward->id], base_path());
                $process->setTimeout(30)->setInput($input)->start();
                $workers[] = [$process, $input];
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, '"ready"')), $process->getErrorOutput());
            }
            // Both processes are started and waiting before either purchase begins.
            foreach ($workers as [$process, $input]) {
                $input->write("BUY\n");
                $input->close();
            }
            $events = [];
            foreach ($workers as [$process]) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $events[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR)['event'];
            }
            sort($events);
            $this->assertSame(['purchased', 'rejected'], $events);
            $this->assertSame(1, RewardPurchase::where('user_id', $user->id)->count());
            $this->assertSame(0, app(RewardService::class)->getAvailableBaxx($user));
        } finally {
            foreach ($workers as [$process, $input]) {
                $input->close();
                $process->stop();
            }
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->committed && isset($this->app)) {
                $this->artisan('migrate:fresh')->assertSuccessful();
            }
        } finally {
            parent::tearDown();
        }
    }
}
