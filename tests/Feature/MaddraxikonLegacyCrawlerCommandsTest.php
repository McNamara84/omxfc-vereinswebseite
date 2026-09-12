<?php

namespace Tests\Feature;

use App\Console\Commands\Crawl2012;
use App\Console\Commands\CrawlAbenteurer;
use App\Console\Commands\CrawlHardcovers;
use App\Console\Commands\CrawlMissionMars;
use App\Console\Commands\CrawlNovels;
use App\Console\Commands\CrawlVolkDerTiefe;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class MaddraxikonLegacyCrawlerCommandsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('commands')]
    public function test_legacy_command_delegates_to_safe_refresh(string $class, string $series): void
    {
        $command = Mockery::mock($class)->makePartial();
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
        $command->shouldReceive('call')
            ->once()
            ->with('books:refresh', ['--series' => $series])
            ->andReturn(Command::SUCCESS);

        $this->assertSame(Command::SUCCESS, $command->handle());
    }

    /** @return iterable<string, array{class-string<Command>, string}> */
    public static function commands(): iterable
    {
        yield 'all via crawlnovels' => [CrawlNovels::class, 'all'];
        yield 'hardcovers' => [CrawlHardcovers::class, 'hardcovers'];
        yield 'mission mars' => [CrawlMissionMars::class, 'missionmars'];
        yield 'volk der tiefe' => [CrawlVolkDerTiefe::class, 'volkdertiefe'];
        yield '2012' => [Crawl2012::class, '2012'];
        yield 'abenteurer' => [CrawlAbenteurer::class, 'abenteurer'];
    }
}
