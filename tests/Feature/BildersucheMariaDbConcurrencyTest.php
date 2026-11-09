<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Services\RewardService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(BildersucheMariaDbConcurrencyTestFixtures::class);

afterEach(function (): void {
    try {
        if ($this->committed && isset($this->app)) {
            $this->artisan('migrate:fresh')->assertSuccessful();
        }
    } finally {
    }
});

test('parallel first purchases cannot duplicate or overdraw the wallet', function (bool $differentRewards): void {
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
            expect($process->waitUntil(fn ($type, $output) => str_contains($output, '"ready"')))->toBeTrue();
        }
        // Both processes are started and waiting before either purchase begins.
        foreach ($workers as [$process, $input]) {
            $input->write("BUY\n");
            $input->close();
        }
        $events = [];
        foreach ($workers as [$process]) {
            expect($process->wait())->toBe(0);
            $lines = explode("\n", trim($process->getOutput()));
            $events[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR)['event'];
        }
        sort($events);
        expect($events)->toBe(['purchased', 'rejected'])
            ->and(RewardPurchase::where('user_id', $user->id)->count())->toBe(1)
            ->and(app(RewardService::class)->getAvailableBaxx($user))->toBe(0);
    } finally {
        foreach ($workers as [$process, $input]) {
            $input->close();
            $process->stop();
        }
    }
})->with([[false], [true]]);
