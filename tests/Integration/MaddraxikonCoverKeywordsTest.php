<?php

namespace Tests\Integration;

use App\Services\Maddraxikon\MaddraxikonApiClient;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaddraxikonCoverKeywordsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['maddraxikon.base_url' => 'https://wiki.example.test', 'maddraxikon.api_url' => 'https://wiki.example.test/api.php', 'maddraxikon.http.attempts' => 1]);
        Http::preventStrayRequests();
    }

    private function page(string $title, array $keywords = []): array
    {
        return ['namespace' => 6, 'exists' => '1', 'fulltext' => $title, 'printouts' => ['Schlagworte' => $keywords]];
    }

    public function test_fetches_structured_keywords_and_preserves_explicit_empty_lists(): void
    {
        Http::fake(['*' => Http::response(['query' => ['results' => [
            'Datei:001tibi.jpg' => $this->page('Datei:001tibi.jpg', ['Aruula', 'Eisgebirge', 'F-17 Alpha 2', 'Matthew Drax', 'Taratze', 'Schwert']),
            'Datei:002tibi.jpg' => $this->page('Datei:002tibi.jpg', ['Bolluna', 'Matthew Drax', 'Jandra', 'Wulfane']),
            'Datei:Empty.jpg' => $this->page('Datei:Empty.jpg'),
        ]]])]);
        $result = app(MaddraxikonApiClient::class)->coverKeywords(['File:001tibi.jpg', 'Datei:002tibi.jpg', 'Datei:Empty.jpg', 'Datei:Missing.jpg']);
        $this->assertCount(6, $result['File:001tibi.jpg']['keywords']);
        $this->assertCount(4, $result['Datei:002tibi.jpg']['keywords']);
        $this->assertSame([], $result['Datei:Empty.jpg']['keywords']);
        $this->assertNull($result['Datei:Missing.jpg']['keywords']);
        $this->assertNotNull($result['Datei:Missing.jpg']['error']);
        Http::assertSent(fn ($request) => $request['action'] === 'ask' && str_contains($request['query'], '[[Datei:001tibi.jpg]]') && ! isset($request['pageids']));
    }

    public function test_follows_smw_continuation(): void
    {
        Http::fakeSequence()->push(['query' => ['results' => ['Datei:A.jpg' => $this->page('Datei:A.jpg', ['Aruula'])]], 'query-continue-offset' => 1])
            ->push(['query' => ['results' => ['Datei:B.jpg' => $this->page('Datei:B.jpg', ['Drax'])]]]);
        $result = app(MaddraxikonApiClient::class)->coverKeywords(['Datei:A.jpg', 'Datei:B.jpg']);
        $this->assertSame(['Drax'], $result['Datei:B.jpg']['keywords']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request['query'], '|offset=1'));
    }

    #[DataProvider('badPayloads')]
    public function test_invalid_or_partial_responses_never_become_empty_keywords(array $payload): void
    {
        Http::fake(['*' => Http::response($payload)]);
        $result = app(MaddraxikonApiClient::class)->coverKeywords(['Datei:A.jpg']);
        $this->assertNull($result['Datei:A.jpg']['keywords']);
        $this->assertNotEmpty($result['Datei:A.jpg']['error']);
    }

    public static function badPayloads(): array
    {
        $page = ['namespace' => 6, 'exists' => '1', 'fulltext' => 'Datei:A.jpg', 'printouts' => ['Schlagworte' => []]];

        return [[[]], [['query' => ['results' => []], 'warnings' => ['truncated']]], [['error' => ['code' => 'unknown_action']]],
            [['query' => ['results' => ['Datei:A.jpg' => 'invalid']]]],
            [['query' => ['results' => [$page]]]],
            [['query' => ['results' => ['Datei:A.jpg' => $page], 'errors' => ['incomplete']]]],
            [['query' => ['results' => ['Datei:A.jpg' => $page], 'warnings' => ['truncated']]]],
            [['query' => ['results' => ['Datei:A.jpg' => array_replace($page, ['namespace' => 0])]]]],
            [['query' => ['results' => ['Datei:A.jpg' => array_replace($page, ['exists' => false])]]]],
            [['query' => ['results' => ['Datei:A.jpg' => array_replace($page, ['fulltext' => 'Datei:B.jpg'])]]]],
            [['query' => ['results' => ['Datei:A.jpg' => array_replace($page, ['printouts' => []])]]]],
            [['query' => ['results' => ['Datei:A.jpg' => array_replace($page, ['printouts' => ['Schlagworte' => [null]]])]]]],
            [['query' => ['results' => ['Datei:Unexpected.jpg' => $page]]]],
            [['query' => ['results' => ['Datei:A.jpg' => $page]], 'query-continue-offset' => 0]],
            [['query' => ['results' => ['Datei:A.jpg' => $page]], 'query-continue-offset' => 251]],
            [['query' => ['results' => ['Datei:A.jpg' => $page]], 'query-continue-offset' => '1']]];
    }

    public function test_repeated_pages_in_a_continuation_invalidate_the_whole_batch(): void
    {
        $page = ['query' => ['results' => ['Datei:A.jpg' => $this->page('Datei:A.jpg', ['Aruula'])]]];
        Http::fakeSequence()->push($page + ['query-continue-offset' => 1])->push($page);
        $result = app(MaddraxikonApiClient::class)->coverKeywords(['Datei:A.jpg', 'Datei:B.jpg']);
        foreach ($result as $source) {
            $this->assertNull($source['keywords']);
            $this->assertNotEmpty($source['error']);
        }
        Http::assertSentCount(2);
    }

    public function test_aliases_share_one_result_and_invalid_keywords_do_not_discard_other_files(): void
    {
        Http::fake(['*' => Http::response(['query' => ['results' => [
            'Datei:Alpha Beta.jpg' => $this->page('Datei:Alpha Beta.jpg', ['Aruula']),
            'Datei:Broken.jpg' => $this->page('Datei:Broken.jpg', [null]),
        ]]])]);
        $result = app(MaddraxikonApiClient::class)->coverKeywords(['File:alpha_Beta.jpg', 'Datei:Alpha Beta.jpg', 'Datei:Broken.jpg']);
        $this->assertSame(['Aruula'], $result['File:alpha_Beta.jpg']['keywords']);
        $this->assertSame($result['File:alpha_Beta.jpg'], $result['Datei:Alpha Beta.jpg']);
        $this->assertNull($result['Datei:Broken.jpg']['keywords']);
        Http::assertSentCount(1);
    }

    public function test_unsafe_titles_are_not_sent_and_empty_input_has_no_network_calls(): void
    {
        Http::fake();
        $client = app(MaddraxikonApiClient::class);
        $this->assertSame([], $client->coverKeywords([]));
        $this->assertNull($client->coverKeywords(['File:foo]] OR [[+'])['File:foo]] OR [[+']['keywords']);
        Http::assertNothingSent();
    }

    public function test_batches_and_deduplicates_titles_and_reports_http_failures(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $titles = array_map(fn ($i) => "Datei:{$i}.jpg", range(1, 26));
        $result = app(MaddraxikonApiClient::class)->coverKeywords([...$titles, 'File:1.jpg']);
        $this->assertCount(27, $result);
        $this->assertNull($result['File:1.jpg']['keywords']);
        Http::assertSentCount(2);
    }
}
