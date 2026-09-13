<?php

namespace Tests\Unit;

use App\Exceptions\MaddraxikonCrawlException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MaddraxikonCrawlException::class)]
class MaddraxikonCrawlExceptionTest extends TestCase
{
    public function test_it_forwards_the_previous_throwable(): void
    {
        $previous = new RuntimeException('Ursache');

        $exception = new MaddraxikonCrawlException('Fehler', previous: $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
