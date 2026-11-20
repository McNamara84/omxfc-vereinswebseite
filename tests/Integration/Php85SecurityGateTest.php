<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

pest()->extend(TestCase::class);

test('the PHP 8.5 security gate enforces the patched version boundary', function (string $version, int $expectedExitCode): void {
    $fixture = dirname(__DIR__).'/Fixtures/Php85SecurityGate/run.php';
    $process = new Process([PHP_BINARY, '-n', $fixture, $version], sys_get_temp_dir());
    $process->setTimeout(10)->run();

    expect($process->getExitCode())->toBe($expectedExitCode, $process->getOutput().$process->getErrorOutput())
        ->and($process->getOutput())->toBe($expectedExitCode === 0
            ? "Verified PHP {$version} (8.5 branch, minimum 8.5.13).\n"
            : '')
        ->and($process->getErrorOutput())->toBe($expectedExitCode === 1
            ? "This job requires PHP 8.5 >= 8.5.13; running PHP {$version}.\n"
            : '');
})->with([
    'accept the patched boundary' => ['8.5.13', 0],
    'reject the previous patch' => ['8.5.12', 1],
    'accept later patches' => ['8.5.14', 0],
    'reject a prerelease below the boundary' => ['8.5.13RC1', 1],
    'reject the previous minor' => ['8.4.99', 1],
    'reject the next minor' => ['8.6.0', 1],
    'reject a different major with the same minor' => ['9.5.13', 1],
]);

test('the standalone security gate uses the actual interpreter despite version arguments and environment variables', function (): void {
    $root = dirname(__DIR__, 2);
    $reference = new Process([PHP_BINARY, '-n', $root.'/tests/Fixtures/Php85SecurityGate/run.php', PHP_VERSION], sys_get_temp_dir());
    $reference->setTimeout(10)->run();

    $actual = new Process([PHP_BINARY, '-n', $root.'/tools/ci/verify-php85.php', '9.5.13'], sys_get_temp_dir(), [
        'PHP_VERSION' => '9.5.13',
        'PHP_MAJOR_VERSION' => '9',
        'PHP_MINOR_VERSION' => '5',
    ]);
    $actual->setTimeout(10)->run();

    expect($actual->getExitCode())->toBe($reference->getExitCode())
        ->and($actual->getOutput())->toBe($reference->getOutput())
        ->and($actual->getErrorOutput())->toBe($reference->getErrorOutput());
});
