<?php

namespace Tests\Unit;

use App\Exceptions\MaddraxikonCrawlException;
use App\Services\Maddraxikon\MaddraxikonReleaseDateParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(MaddraxikonReleaseDateParser::class)]
class MaddraxikonReleaseDateParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalizes_german_month_only_dates(): void
    {
        $parser = new MaddraxikonReleaseDateParser;

        $this->assertSame('2004-06-01', $parser->parse('Juni 2004')->toDateString());
        $this->assertSame('2004-12-01', $parser->parse('Dezember 2004')->toDateString());
        $this->assertSame('2004-03-12', $parser->parse('12. März 2004')->toDateString());
    }

    public function test_it_rejects_an_unparseable_date_and_preserves_the_cause(): void
    {
        try {
            (new MaddraxikonReleaseDateParser)->parse('irgendwann demnächst');
            $this->fail('Expected date parsing to fail.');
        } catch (MaddraxikonCrawlException $exception) {
            $this->assertStringContainsString('nicht sicher ausgewertet', $exception->getMessage());
            $this->assertNotNull($exception->getPrevious());
        }
    }
}
