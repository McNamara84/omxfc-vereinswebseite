<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Maddraxikon\OAuthAttemptStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

use function base64_encode;
use function bin2hex;
use function config;
use function random_bytes;
use function sort;
use function sprintf;

pest()->extend(TestCase::class)->use(MaddraxikonOAuthAttemptStoreMariaDbConcurrencyTestFixtures::class);

covers(OAuthAttemptStore::class);

afterEach(function (): void {
    try {
        if ($this->committedTestDatabase && isset($this->app)) {
            $exitCode = $this->artisan('migrate:fresh')->execute();
            if ($exitCode !== 0) {
                throw new RuntimeException(sprintf('Die MariaDB-Testdatenbank konnte nicht bereinigt werden (Exit %d).', $exitCode));
            }
        }
    } finally {
    }
});

test('database cache attempt is consumed exactly once across processes', function (): void {
    $this->requireSafeMariaDbTestDatabase();
    $appKey = 'base64:'.base64_encode(random_bytes(32));
    $cachePrefix = 'oauth_attempt_concurrency_'.bin2hex(random_bytes(8)).'_';
    config(['app.key' => $appKey, 'cache.default' => 'database', 'cache.prefix' => $cachePrefix]);
    app()->forgetInstance('encrypter');
    Crypt::clearResolvedInstance('encrypter');
    Cache::setDefaultDriver('database');
    Cache::forgetDriver('database');
    expect(Cache::getDefaultDriver())->toBe('database');
    $user = User::factory()->create();
    $sessionId = 'shared-oauth-session';
    $attempt = app(OAuthAttemptStore::class)->create($user, $sessionId);
    $cacheKey = 'maddraxikon:oauth:attempt:'.hash('sha256', $attempt->state);
    expect(Cache::has($cacheKey))->toBeTrue()
        ->and(DB::table('cache')->where('key', $cachePrefix.$cacheKey)->exists())->toBeTrue()
        ->and(DB::transactionLevel())->toBe(1);
    DB::connection()->commit();
    $this->committedTestDatabase = true;
    $environment = $this->workerEnvironment($appKey, $cachePrefix);
    $first = [];
    $second = [];
    try {
        $first = $this->startWorker($user->id, $attempt->state, $sessionId, $environment);
        $second = $this->startWorker($user->id, $attempt->state, $sessionId, $environment);
        $firstReady = $this->readWorkerResult($first, 10);
        $secondReady = $this->readWorkerResult($second, 10);
        expect($firstReady['event'] ?? null)->toBe('ready')
            ->and($secondReady['event'] ?? null)->toBe('ready');
        $this->sendWorkerCommand($first, 'CONSUME');
        $this->sendWorkerCommand($second, 'CONSUME');
        $firstResult = $this->readWorkerResult($first, 10);
        $secondResult = $this->readWorkerResult($second, 10);
        $firstExitCode = $this->finishWorker($first);
        $secondExitCode = $this->finishWorker($second);
    } finally {
        $this->terminateWorker($first);
        $this->terminateWorker($second);
    }
    expect($firstExitCode)->toBe(0)
        ->and($secondExitCode)->toBe(0)
        ->and($firstResult['ok'] ?? false)->toBeTrue()
        ->and($secondResult['ok'] ?? false)->toBeTrue()
        ->and($firstResult['event'] ?? null)->toBe('consumed')
        ->and($secondResult['event'] ?? null)->toBe('consumed');
    $outcomes = [$firstResult['outcome'] ?? null, $secondResult['outcome'] ?? null];
    sort($outcomes);
    expect($outcomes)->toBe(['invalid', 'success']);
    $success = ($firstResult['outcome'] ?? null) === 'success' ? $firstResult : $secondResult;
    $invalid = ($firstResult['outcome'] ?? null) === 'invalid' ? $firstResult : $secondResult;
    expect($success['state'] ?? null)->toBe($attempt->state)
        ->and($success['code_verifier'] ?? null)->toBe($attempt->codeVerifier)
        ->and($success['consent_version'] ?? null)->toBe($attempt->consentVersion)
        ->and($success['consented_at'] ?? null)->toBe($attempt->consentedAt->getTimestamp())
        ->and($invalid['exception'] ?? null)->toBe('InvalidOAuthAttemptException')
        ->and(Cache::has($cacheKey))->toBeFalse()
        ->and(DB::table('cache')->where('key', $cachePrefix.$cacheKey)->exists())->toBeFalse();
});
