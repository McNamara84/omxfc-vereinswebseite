<?php

declare(strict_types=1);

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
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);
dataset('MaddraxikonLegacyCrawlerCommandsTest.commands', static function (): iterable {
    yield 'all via crawlnovels' => [CrawlNovels::class, 'all'];
    yield 'hardcovers' => [CrawlHardcovers::class, 'hardcovers'];
    yield 'mission mars' => [CrawlMissionMars::class, 'missionmars'];
    yield 'volk der tiefe' => [CrawlVolkDerTiefe::class, 'volkdertiefe'];
    yield '2012' => [Crawl2012::class, '2012'];
    yield 'abenteurer' => [CrawlAbenteurer::class, 'abenteurer'];
});
test('legacy command delegates to safe refresh', function (string $class, string $series): void {
    $command = Mockery::mock($class)->makePartial();
    $command->setLaravel($this->app);
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
    $command->shouldReceive('call')->once()->with('books:refresh', ['--series' => $series])->andReturn(Command::SUCCESS);
    expect($command->handle())->toBe(Command::SUCCESS);
})->with('MaddraxikonLegacyCrawlerCommandsTest.commands');
