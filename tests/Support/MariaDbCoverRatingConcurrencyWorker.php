<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Services\CoverRatings\CoverRatingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @param array<string, mixed> $payload */
function emitCoverRatingResult(array $payload): void
{
    fwrite(STDOUT, json_encode(
        $payload,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
    ).PHP_EOL);
    fflush(STDOUT);
}

function assertSafeCoverRatingDatabase(): void
{
    $connection = (string) config('database.default');
    $driver = (string) config("database.connections.{$connection}.driver");
    $database = (string) config("database.connections.{$connection}.database");

    if (
        ! app()->environment('testing')
        || ! in_array($driver, ['mysql', 'mariadb'], true)
        || $database !== 'omxfc_maddraxikon_test'
    ) {
        throw new RuntimeException("Unsichere Worker-Datenbank verweigert: {$driver}/{$database}.");
    }

    $version = (string) (DB::selectOne('SELECT VERSION() AS version')->version ?? '');

    if (! str_contains($version, 'MariaDB')) {
        throw new RuntimeException('Der Cover-Bewertungs-Worker darf nur gegen MariaDB laufen.');
    }
}

try {
    assertSafeCoverRatingDatabase();
    config(['logging.default' => 'null']);

    $userId = (int) ($argv[1] ?? 0);
    $coverId = (int) ($argv[2] ?? 0);

    if ($userId < 1 || $coverId < 1) {
        throw new RuntimeException('Nutzer- und Cover-ID muessen positiv sein.');
    }

    emitCoverRatingResult(['ok' => true, 'event' => 'ready']);

    if (trim((string) fgets(STDIN)) !== 'RATE') {
        throw new RuntimeException('Der Worker erhielt kein RATE-Kommando.');
    }

    $user = User::query()->findOrFail($userId);
    $result = app(CoverRatingService::class)->rate($user, $coverId, 5);

    emitCoverRatingResult([
        'ok' => true,
        'event' => 'rated',
        'rating_id' => $result['rating']->id,
        'awarded_baxx' => $result['awarded_baxx'],
    ]);
} catch (Throwable $exception) {
    emitCoverRatingResult([
        'ok' => false,
        'event' => 'failed',
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ]);

    exit(1);
}
