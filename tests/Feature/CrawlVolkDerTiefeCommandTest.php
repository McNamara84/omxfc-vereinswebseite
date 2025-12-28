<?php

namespace Tests\Feature;

use App\Console\Commands\CrawlVolkDerTiefe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Illuminate\Console\OutputStyle;
use Tests\TestCase;

class CrawlVolkDerTiefeCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = base_path('storage/testing');
        $this->app->useStoragePath($this->testStoragePath);
        Storage::fake('private');
        File::ensureDirectoryExists(dirname(Storage::disk('private')->path('volkdertiefe.json')));
        File::ensureDirectoryExists($this->testStoragePath.'/framework/views');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStoragePath);
        Mockery::close();

        parent::tearDown();
    }

    public function test_command_writes_filtered_sorted_json(): void
    {
        $categoryHtml = <<<HTML
            <html><body>
                <div id="mw-pages">
                    <a href="/wiki/DVT_01">DVT 01</a>
                    <a href="/wiki/DVT_02">DVT 02</a>
                </div>
            </body></html>
        HTML;

        $novelHtml = <<<HTML
            <html><body>
                <table>
                    <tr><td>Erstmals&nbsp;erschienen:</td><td>2020-01-01</td></tr>
                    <tr><td>Zyklus:</td><td>Testzyklus (12 Bände)</td></tr>
                    <tr><td>Titel:</td><th>Der Ruf</th></tr>
                    <tr><td>Text:</td><td>Autor A, Autor B</td></tr>
                    <tr><td>Personen:</td><td>Person 1, Person 2</td></tr>
                    <tr><td>Schlagworte:</td><td>Signal, Tiefe</td></tr>
                    <tr><td>Handlungsort:</td><td>Atlantis, Kueste</td></tr>
                </table>
                <b>1</b>
                <div class="voteboxrate">4.5</div>
                <span class="rating-total">(3 Stimmen)</span>
            </body></html>
        HTML;

        $futureHtml = <<<HTML
            <html><body>
                <table>
                    <tr><td>Erstmals&nbsp;erschienen:</td><td>2999-12-31</td></tr>
                </table>
                <b>2</b>
                <div class="voteboxrate">4.9</div>
                <span class="rating-total">(eine Stimme)</span>
            </body></html>
        HTML;

        $command = Mockery::mock(CrawlVolkDerTiefe::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

        $command->shouldReceive('getUrlContent')
            ->atLeast()
            ->times(1)
            ->andReturnUsing(function (string $url) use ($categoryHtml, $novelHtml, $futureHtml) {
            return match (true) {
                str_contains($url, 'Kategorie:Das_Volk_der_Tiefe') => $categoryHtml,
                str_contains($url, 'DVT_01') => $novelHtml,
                default => $futureHtml,
            };
        });

        $result = $command->handle();

        $this->assertSame(0, $result);

        $this->assertTrue(Storage::disk('private')->exists('volkdertiefe.json'));

        $data = json_decode(Storage::disk('private')->get('volkdertiefe.json'), true);
        $this->assertCount(1, $data, 'Future releases should be skipped');
        $this->assertSame([
            'nummer' => 1,
            'evt' => '2020-01-01',
            'zyklus' => 'Testzyklus',
            'titel' => 'Der Ruf',
            'text' => ['Autor A', 'Autor B'],
            'bewertung' => 4.5,
            'stimmen' => 3,
            'personen' => ['Person 1', 'Person 2'],
            'schlagworte' => ['Signal', 'Tiefe'],
            'orte' => ['Atlantis', 'Kueste'],
        ], $data[0]);
    }
}
