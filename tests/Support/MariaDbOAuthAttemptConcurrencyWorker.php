<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Services\Maddraxikon\Exceptions\InvalidOAuthAttemptException;
use App\Services\Maddraxikon\OAuthAttemptStore;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

use function class_basename;
use function config;
use function fclose;
use function fflush;
use function fgets;
use function fwrite;
use function getenv;
use function in_array;
use function json_encode;
use function sprintf;
use function str_contains;
use function trim;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/**
 * @param  array<string, mixed>  $payload
 */
function emitOAuthAttemptWorkerResult(array $payload): void
{
    fwrite(
        STDOUT,
        json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL
    );
    fflush(STDOUT);
}

/**
 * Refuse to run against any database except the dedicated integration-test
 * database. The cache and its distributed lock use this same connection.
 */
function assertSafeOAuthAttemptWorkerEnvironment(): void
{
    $connection = (string) config('database.default');
    $driver = (string) config("database.connections.{$connection}.driver");
    $database = (string) config("database.connections.{$connection}.database");
    $configuredAppKey = (string) config('app.key');
    $environmentAppKey = (string) getenv('APP_KEY');

    if (
        ! app()->environment('testing')
        || ! in_array($driver, ['mysql', 'mariadb'], true)
        || $database !== 'omxfc_maddraxikon_test'
    ) {
        throw new RuntimeException(sprintf(
            'Unsichere Worker-Datenbank verweigert: %s/%s (%s).',
            $driver,
            $database,
            app()->environment()
        ));
    }

    $version = (string) (DB::selectOne(
        'SELECT VERSION() AS database_version'
    )->database_version ?? '');

    if (! str_contains($version, 'MariaDB')) {
        throw new RuntimeException(
            'Der OAuth-Nebenlaeufigkeits-Worker darf nur gegen MariaDB laufen.'
        );
    }

    if (
        config('cache.default') !== 'database'
        || $configuredAppKey === ''
        || $configuredAppKey !== $environmentAppKey
    ) {
        throw new RuntimeException(
            'Der Worker benoetigt CACHE_STORE=database und einen gemeinsamen APP_KEY.'
        );
    }
}

try {
    assertSafeOAuthAttemptWorkerEnvironment();

    config(['logging.default' => 'null']);

    $userId = (int) ($argv[1] ?? 0);
    $state = (string) ($argv[2] ?? '');
    $sessionId = (string) ($argv[3] ?? '');

    if ($userId < 1 || $state === '' || $sessionId === '') {
        throw new RuntimeException(
            'Nutzer, OAuth-State und Session-Bindung sind erforderlich.'
        );
    }

    $user = User::query()->findOrFail($userId);

    emitOAuthAttemptWorkerResult([
        'ok' => true,
        'event' => 'ready',
    ]);

    if (trim((string) fgets(STDIN)) !== 'CONSUME') {
        throw new RuntimeException(
            'Der OAuth-Nebenlaeufigkeits-Worker wurde abgebrochen.'
        );
    }

    try {
        $attempt = app(OAuthAttemptStore::class)->consume(
            $state,
            $user,
            $sessionId
        );
    } catch (InvalidOAuthAttemptException $exception) {
        emitOAuthAttemptWorkerResult([
            'ok' => true,
            'event' => 'consumed',
            'outcome' => 'invalid',
            'exception' => class_basename($exception),
        ]);

        exit(0);
    }

    emitOAuthAttemptWorkerResult([
        'ok' => true,
        'event' => 'consumed',
        'outcome' => 'success',
        'state' => $attempt->state,
        'code_verifier' => $attempt->codeVerifier,
        'consent_version' => $attempt->consentVersion,
        'consented_at' => $attempt->consentedAt->getTimestamp(),
    ]);

    exit(0);
} catch (Throwable $exception) {
    emitOAuthAttemptWorkerResult([
        'ok' => false,
        'exception' => class_basename($exception),
        'message' => $exception->getMessage(),
    ]);

    fclose(STDIN);
    exit(1);
}
