<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

pest()->extend(TestCase::class);

test('TIA synchronizes real files and preserves unsupported stream failures', function (): void {
    $root = dirname(__DIR__, 2);
    $process = new Process([PHP_BINARY, $root.'/tests/Fixtures/Tia/native-stream-sync.php'], $root);
    $process->setTimeout(30)->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput())
        ->and($process->getErrorOutput())->toBe('');
    $results = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    expect($results)->toBe([
        'native' => ['recording' => false, 'synced' => true, 'contents' => 'native'],
        'recording' => ['recording' => true, 'synced' => true, 'contents' => 'recording'],
        'unsupported' => false,
        'closed' => true,
    ]);
});
