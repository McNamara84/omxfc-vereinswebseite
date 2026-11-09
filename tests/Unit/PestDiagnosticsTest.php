<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('Pest diagnostic commands preserve actual execution counts', function (string $script, string $mode, int $attempts, bool $successful, int $controlAttempts): void {
    $root = dirname(__DIR__, 2);
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $command = $composer['scripts'][$script];
    expect($command)->toStartWith('@php ');
    $counter = tempnam(sys_get_temp_dir(), 'pest-diagnostic-');
    if ($counter === false) {
        throw new RuntimeException('Cannot create the diagnostic counter.');
    }

    try {
        $process = new Process([
            PHP_BINARY,
            ...explode(' ', substr($command, 5)),
            '--configuration='.$root.'/tests/Fixtures/PestDiagnostics/phpunit.xml',
            '--no-tia',
        ], $root, [
            'PEST_DIAGNOSTIC_COUNTER' => $counter,
            'PEST_DIAGNOSTIC_MODE' => $mode,
        ]);
        $process->setTimeout(30)->run();

        expect($process->isSuccessful())->toBe($successful, $process->getOutput().$process->getErrorOutput())
            ->and((int) file_get_contents($counter))->toBe($attempts)
            ->and((int) file_get_contents($counter.'.control'))->toBe($controlAttempts);
    } finally {
        unlink($counter);
        if (is_file($counter.'.control')) {
            unlink($counter.'.control');
        }
    }
})->with([
    'repeat executes twice' => ['test:stability:repeat', 'repeat', 2, true, 2],
    'retry actually reruns the failure' => ['test:stability:retry', 'retry', 2, true, 1],
    'repeat preserves an earlier failure' => ['test:stability:repeat', 'retry', 2, false, 2],
    'persistent failures stop after the configured attempts' => ['test:stability:retry', 'always-fail', 2, false, 1],
    'normal run preserves the initial failure' => ['test', 'retry', 1, false, 1],
]);
