<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\MaddraxikonCrawlException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

pest()->extend(TestCase::class);

covers(MaddraxikonCrawlException::class);

test('it forwards the previous throwable', function (): void {
    $previous = new RuntimeException('Ursache');
    $exception = new MaddraxikonCrawlException('Fehler', previous: $previous);
    expect($exception->getPrevious())->toBe($previous);
});
