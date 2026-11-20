<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

pest()->extend(TestCase::class);

test('the PHP 8.6 security gate requires a stable supported release', function (string $version, int $expectedExitCode): void {
    $fixture = dirname(__DIR__).'/Fixtures/Php86SecurityGate/run.php';
    $process = new Process([PHP_BINARY, '-n', $fixture, $version], sys_get_temp_dir());
    $process->setTimeout(10)->run();

    expect($process->getExitCode())->toBe($expectedExitCode, $process->getOutput().$process->getErrorOutput())
        ->and($process->getOutput())->toBe($expectedExitCode === 0
            ? "Verified PHP {$version} (stable 8.6 branch, minimum 8.6.0).\n"
            : '')
        ->and($process->getErrorOutput())->toBe($expectedExitCode === 1
            ? "This job requires stable PHP 8.6 >= 8.6.0; running PHP {$version}.\n"
            : '');
})->with([
    'accept the stable boundary' => ['8.6.0', 0],
    'accept later patches' => ['8.6.1', 0],
    'reject a release candidate' => ['8.6.0RC1', 1],
    'reject a later patch release candidate' => ['8.6.1RC1', 1],
    'reject a development build' => ['8.6.1-dev', 1],
    'reject the previous minor' => ['8.5.99', 1],
    'reject older branches' => ['8.4.99', 1],
    'reject the next minor' => ['8.7.0', 1],
    'reject a different major with the same minor' => ['9.6.0', 1],
]);

test('the standalone security gate uses the actual interpreter despite version arguments and environment variables', function (): void {
    $root = dirname(__DIR__, 2);
    $reference = new Process([PHP_BINARY, '-n', $root.'/tests/Fixtures/Php86SecurityGate/run.php', PHP_VERSION], sys_get_temp_dir());
    $reference->setTimeout(10)->run();

    $actual = new Process([PHP_BINARY, '-n', $root.'/tools/ci/verify-php86.php', '9.6.0'], sys_get_temp_dir(), [
        'PHP_VERSION' => '9.6.0',
        'PHP_MAJOR_VERSION' => '9',
        'PHP_MINOR_VERSION' => '6',
    ]);
    $actual->setTimeout(10)->run();

    expect($actual->getExitCode())->toBe($reference->getExitCode())
        ->and($actual->getOutput())->toBe($reference->getOutput())
        ->and($actual->getErrorOutput())->toBe($reference->getErrorOutput());
});
