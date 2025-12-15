<?php

namespace Tests\Feature;

use App\Console\Commands\CrawlNovels;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use ReflectionClass;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class CrawlNovelsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = base_path('storage/testing');
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath . '/app/private');
        File::ensureDirectoryExists($this->testStoragePath . '/framework/views');
        config(['filesystems.disks.private.root' => $this->testStoragePath . '/app/private']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStoragePath);
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_article_urls_recursively_collects_links(): void
    {
        $htmlPage1 = '<div id="mw-pages"><a href="wiki/A1">A1</a></div><a href="next">nächste Seite</a>';
        $htmlPage2 = '<div id="mw-pages"><a href="wiki/A2">A2</a></div>';

        $file1 = storage_path('app/private/page1.html');
        $file2 = storage_path('app/private/page2.html');
        File::put($file1, $htmlPage1);
        File::put($file2, $htmlPage2);

        $command = new CrawlNovels();

        $ref = new ReflectionClass($command);
        $getUrlContent = $ref->getMethod('getUrlContent');
        $getUrlContent->setAccessible(true);
        
        // Ensure method can read local files
        $this->assertIsString($getUrlContent->invoke($command, 'file://' . $file1));

        $method = $ref->getMethod('getArticleUrls');
        $method->setAccessible(true);

        $urls = $method->invoke($command, 'file://' . $file1);

        $this->assertSame([
            'https://de.maddraxikon.com/wiki/A1',
        ], $urls);
    }

    public function test_get_heftroman_info_parses_html(): void
    {
        $html = "<b>123</b>
            <table>
                <tr><td>Erstmals&nbsp;erschienen:</td><td>2024-01</td></tr>
                <tr><td>Zyklus:</td><td>Testzyklus (1)</td></tr>
                <tr><td>Titel:</td><th>Der Roman</th></tr>
                <tr><td>Text:</td><td>Autor1, Autor2</td></tr>
                <tr><td>Personen:</td><td>P1, P2</td></tr>
                <tr><td>Schlagworte:</td><td>S1, S2</td></tr>
                <tr><td>Handlungsort:</td><td>O1, O2</td></tr>
            </table>
            <div class=\"voteboxrate\">4.5</div>
            <span class=\"rating-total\">3 Stimmen</span>";

        $file = storage_path('app/private/article.html');
        File::put($file, $html);

        $command = new CrawlNovels();
        $ref = new ReflectionClass($command);
        $method = $ref->getMethod('getHeftromanInfo');
        $method->setAccessible(true);

        $info = $method->invoke($command, 'file://' . $file);

        $this->assertSame([
            '123',
            '2024-01',
            'Testzyklus',
            '4.5',
            '3',
            'Der Roman',
            ['Autor1', 'Autor2'],
            ['P1', 'P2'],
            ['S1', 'S2'],
            ['O1', 'O2'],
        ], $info);
    }

    public function test_write_heftromane_sorts_and_writes_json(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 6, 1));
        $command = new CrawlNovels();
        $ref = new ReflectionClass($command);
        $method = $ref->getMethod('writeHeftromane');
        $method->setAccessible(true);

        $data = [
            [1, '2024-07-01', null, '4.0', '1', 'Future', null, null, null, null],
            [2, '2024-05-01', null, '3.0', '2', 'Past', null, null, null, null],
            [3, '2024-06-01', null, 0, '0', 'TodayUnrated', null, null, null, null],
        ];
        $file = storage_path('app/private/maddrax.json');

        $result = $method->invoke($command, $data, $file);

        $this->assertTrue($result);
        $json = json_decode(File::get($file), true);
        $numbers = array_column($json, 'nummer');
        $this->assertSame([2,3], $numbers); // future release skipped, sorted
        Carbon::setTestNow();
    }

    public function test_handle_triggers_all_related_crawlers(): void
    {
        $command = Mockery::mock(CrawlNovels::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

        $command->shouldReceive('getArticleUrls')->once()->andReturn(['https://example.com/article']);
        $command->shouldReceive('getHeftromanInfo')->once()->andReturn([
            1,
            '2024-01-01',
            'Zyklus',
            '4.0',
            '2',
            'Titel',
            ['Autor'],
            ['Person'],
            ['Schlagwort'],
            ['Ort'],
        ]);
        $command->shouldReceive('writeHeftromane')->once()->andReturn(true);

        $command->shouldReceive('call')->once()->with(\App\Console\Commands\CrawlMissionMars::class)->andReturn(Command::SUCCESS);
        $command->shouldReceive('call')->once()->with(\App\Console\Commands\Crawl2012::class)->andReturn(Command::SUCCESS);
        $command->shouldReceive('call')->once()->with(\App\Console\Commands\CrawlVolkDerTiefe::class)->andReturn(Command::SUCCESS);
        $command->shouldReceive('call')->once()->with(\App\Console\Commands\CrawlHardcovers::class)->andReturn(Command::SUCCESS);

        $this->assertSame(Command::SUCCESS, $command->handle());
    }
}
