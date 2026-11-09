<?php

declare(strict_types=1);

test('records each real diagnostic attempt [ä+?]', function (): void {
    $counter = getenv('PEST_DIAGNOSTIC_COUNTER');
    if (! is_string($counter) || ! is_file($counter)) {
        throw new RuntimeException('This fixture requires a temporary counter file.');
    }

    $attempt = (int) file_get_contents($counter) + 1;
    file_put_contents($counter, (string) $attempt, LOCK_EX);

    $mode = getenv('PEST_DIAGNOSTIC_MODE');
    expect($mode === 'repeat' || ($mode === 'retry' && $attempt >= 2))->toBeTrue();
});

test('passing control case is not retried', function (): void {
    $counter = getenv('PEST_DIAGNOSTIC_COUNTER');
    if (! is_string($counter) || ! is_file($counter)) {
        throw new RuntimeException('This fixture requires a temporary counter file.');
    }

    $control = $counter.'.control';
    $attempt = is_file($control) ? (int) file_get_contents($control) + 1 : 1;
    file_put_contents($control, (string) $attempt, LOCK_EX);
    expect($attempt)->toBeGreaterThan(0);
});
