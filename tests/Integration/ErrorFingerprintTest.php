<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ErrorReporting\ErrorFingerprint;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(ErrorFingerprintTestFixtures::class);

test('it ignores variable exception messages at the same origin', function (): void {
    $service = app(ErrorFingerprint::class);
    $first = $this->exceptionWithMessage('Datensatz 123 fehlt');
    $second = $this->exceptionWithMessage('Datensatz 999 fehlt');
    expect($service->forException($second, 'dashboard', null))->toBe($service->forException($first, 'dashboard', null));
});

test('it distinguishes routes and execution names', function (): void {
    $service = app(ErrorFingerprint::class);
    $exception = $this->exceptionWithMessage('Fehler');
    expect($service->forException($exception, 'profile.show', null))->not->toBe($service->forException($exception, 'dashboard', null))
        ->and($service->forException($exception, null, 'App\Jobs\SecondJob'))->not->toBe($service->forException($exception, null, 'App\Jobs\FirstJob'));
});
