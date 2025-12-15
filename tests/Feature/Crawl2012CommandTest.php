<?php

namespace Tests\Feature;

use App\Console\Commands\Crawl2012;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Tests\TestCase;

class HttpsFixtureStream
{
    public static array $fixtures = [];

    private string $data = '';

    private int $position = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        if (!array_key_exists($path, self::$fixtures)) {
            return false;
        }

        $this->data = self::$fixtures[$path];
        $this->position = 0;

        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr($this->data, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->data);
    }

    public function stream_stat(): array
    {
        return [];
    }
}

class Crawl2012CommandTest extends TestCase
{
    use RefreshDatabase;

    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = base_path('storage/testing');
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath.'/app/private');
        File::ensureDirectoryExists($this->testStoragePath.'/framework/views');
        config(['filesystems.disks.private.root' => $this->testStoragePath.'/app/private']);

        if (in_array('https', stream_get_wrappers(), true)) {
            stream_wrapper_unregister('https');
        }
        stream_wrapper_register('https', HttpsFixtureStream::class);
    }

    protected function tearDown(): void
    {
        stream_wrapper_unregister('https');
        stream_wrapper_restore('https');
        HttpsFixtureStream::$fixtures = [];
        File::deleteDirectory($this->testStoragePath);
        parent::tearDown();
    }

    public function test_get_article_urls_recursively_collects_links(): void
    {
        $pageOneUrl = 'https://de.maddraxikon.com/index.php?title=Kategorie:2012-Heftromane';
        $pageTwoUrl = 'https://de.maddraxikon.com/index.php?title=Kategorie:2012-Heftromane&pagefrom=2';
        $htmlPage1 = '<meta charset="UTF-8"><div id="mw-pages"><a href="wiki/A1">A1</a></div><a href="index.php?title=Kategorie:2012-Heftromane&pagefrom=2">nächste Seite</a>';
        $htmlPage2 = '<div id="mw-pages"><a href="wiki/A2">A2</a></div>';

        HttpsFixtureStream::$fixtures = [$pageOneUrl => $htmlPage1, $pageTwoUrl => $htmlPage2];

        $command = new Crawl2012();

        $ref = new ReflectionClass($command);
        $method = $ref->getMethod('getArticleUrls');
        $method->setAccessible(true);

        $urls = $method->invoke($command, $pageOneUrl);

        $this->assertSame([
            'https://de.maddraxikon.com/wiki/A1',
            'https://de.maddraxikon.com/wiki/A2',
        ], $urls);
    }

    public function test_get_article_urls_ignore_external_next_links(): void
    {
        $pageOneUrl = 'https://de.maddraxikon.com/index.php?title=Kategorie:2012-Heftromane';
        $htmlPage1 = '<meta charset="UTF-8"><div id="mw-pages"><a href="wiki/A1">A1</a></div><a href="https://evil.example.com/page">nächste Seite</a>';

        HttpsFixtureStream::$fixtures = [$pageOneUrl => $htmlPage1];

        $command = new Crawl2012();

        $ref = new ReflectionClass($command);
        $method = $ref->getMethod('getArticleUrls');
        $method->setAccessible(true);

        $urls = $method->invoke($command, $pageOneUrl);

        $this->assertSame([
            'https://de.maddraxikon.com/wiki/A1',
        ], $urls);
    }

    public function test_get_heftroman_info_parses_html(): void
    {
        $html = <<<'HTML'
<b>123</b>
    <table>
        <tr><td>Erstmals&nbsp;erschienen:</td><td>2024-01</td></tr>
        <tr><td>Zyklus:</td><td>Testzyklus (1)</td></tr>
        <tr><td>Titel:</td><th>Der Roman</th></tr>
        <tr><td>Text:</td><td>Autor1, Autor2</td></tr>
        <tr><td>Personen:</td><td>P1, P2</td></tr>
        <tr><td>Schlagworte:</td><td>S1, S2</td></tr>
        <tr><td>Handlungsort:</td><td>O1, O2</td></tr>
    </table>
    <div class="voteboxrate">4.5</div>
    <span class="rating-total">3 Stimmen</span>
HTML;

        $file = storage_path('app/private/article.html');
        File::put($file, $html);

        $command = new Crawl2012();
        $ref = new ReflectionClass($command);
        $method = $ref->getMethod('getHeftromanInfo');
        $method->setAccessible(true);

        $info = $method->invoke($command, 'file://'.$file);

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
        $command = new Crawl2012();
        $ref = new ReflectionClass($command);
        $method = $ref->getMethod('writeHeftromane');
        $method->setAccessible(true);

        $data = [
            [1, '2024-07-01', null, '4.0', '1', 'Future', null, null, null, null],
            [2, '2024-05-01', null, '3.0', '2', 'Past', null, null, null, null],
            [3, '2024-06-01', null, 0, '0', 'TodayUnrated', null, null, null, null],
        ];
        $file = storage_path('app/private/2012.json');

        $result = $method->invoke($command, $data, $file);

        $this->assertTrue($result);
        $json = json_decode(File::get($file), true);
        $numbers = array_column($json, 'nummer');
        $this->assertSame([2, 3], $numbers); // future release skipped, sorted
        Carbon::setTestNow();
    }
}
