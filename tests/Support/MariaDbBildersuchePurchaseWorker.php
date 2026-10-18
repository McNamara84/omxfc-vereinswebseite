<?php

use App\Models\Reward;
use App\Models\User;
use App\Services\RewardService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (! app()->environment('testing') || DB::getDatabaseName() !== 'omxfc_maddraxikon_test'
        || ! str_contains(DB::selectOne('SELECT VERSION() AS version')->version, 'MariaDB')) {
        throw new RuntimeException('Unsafe test database.');
    }
    config(['bildersuche.enabled' => true, 'logging.default' => 'null']);
    echo json_encode(['event' => 'ready']).PHP_EOL;
    fflush(STDOUT);
    if (trim((string) fgets(STDIN)) !== 'BUY') {
        throw new RuntimeException('Missing BUY command.');
    }
    $user = User::findOrFail((int) $argv[1]);
    $reward = Reward::findOrFail((int) $argv[2]);
    $purchase = app(RewardService::class)->purchaseReward($user, $reward);
    echo json_encode(['event' => 'purchased', 'id' => $purchase->id]).PHP_EOL;
} catch (ValidationException $exception) {
    echo json_encode(['event' => 'rejected', 'message' => $exception->getMessage()]).PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage());
    exit(1);
}
