<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\Maddraxikon\MaddraxikonApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonCoverKeywordsTestFixtures::class);
dataset('MaddraxikonCoverKeywordsTest.badPayloads', static function (): array {
    $page = ['namespace' => 6, 'exists' => '1', 'fulltext' => 'Datei:A.jpg', 'printouts' => ['Schlagworte' => []]];

    return [[[]], [['query' => ['results' => []], 'warnings' => ['truncated']]], [['error' => ['code' => 'unknown_action']]], [['query' => ['results' => ['Datei:A.jpg' => 'invalid']]]], [['query' => ['results' => [$page]]]], [['query' => ['results' => ['Datei:A.jpg' => $page], 'errors' => ['incomplete']]]], [['query' => ['results' => ['Datei:A.jpg' => $page], 'warnings' => ['truncated']]]], [['query' => ['results' => ['Datei:A.jpg' => array_replace($page, ['namespace' => 0])]]]], [['query' => ['results' => ['Datei:A.jpg' => array_replace($page, ['exists' => false])]]]], [['query' => ['results' => ['Datei:A.jpg' => array_replace($page, ['fulltext' => 'Datei:B.jpg'])]]]], [['query' => ['results' => ['Datei:A.jpg' => array_replace($page, ['printouts' => []])]]]], [['query' => ['results' => ['Datei:A.jpg' => array_replace($page, ['printouts' => ['Schlagworte' => [null]]])]]]], [['query' => ['results' => ['Datei:Unexpected.jpg' => $page]]]], [['query' => ['results' => ['Datei:A.jpg' => $page]], 'query-continue-offset' => 0]], [['query' => ['results' => ['Datei:A.jpg' => $page]], 'query-continue-offset' => 251]], [['query' => ['results' => ['Datei:A.jpg' => $page]], 'query-continue-offset' => '1']]];
});
beforeEach(function (): void {
    config(['maddraxikon.base_url' => 'https://wiki.example.test', 'maddraxikon.api_url' => 'https://wiki.example.test/api.php', 'maddraxikon.http.attempts' => 1]);
    Http::preventStrayRequests();
});
test('fetches structured keywords and preserves explicit empty lists', function (): void {
    Http::fake(['*' => Http::response(['query' => ['results' => ['Datei:001tibi.jpg' => $this->page('Datei:001tibi.jpg', ['Aruula', 'Eisgebirge', 'F-17 Alpha 2', 'Matthew Drax', 'Taratze', 'Schwert']), 'Datei:002tibi.jpg' => $this->page('Datei:002tibi.jpg', ['Bolluna', 'Matthew Drax', 'Jandra', 'Wulfane']), 'Datei:Empty.jpg' => $this->page('Datei:Empty.jpg')]]])]);
    $result = app(MaddraxikonApiClient::class)->coverKeywords(['File:001tibi.jpg', 'Datei:002tibi.jpg', 'Datei:Empty.jpg', 'Datei:Missing.jpg']);
    expect($result['File:001tibi.jpg']['keywords'])->toHaveCount(6)
        ->and($result['Datei:002tibi.jpg']['keywords'])->toHaveCount(4)
        ->and($result['Datei:Empty.jpg']['keywords'])->toBe([])
        ->and($result['Datei:Missing.jpg']['keywords'])->toBeNull()
        ->and($result['Datei:Missing.jpg']['error'])->not->toBeNull();
    Http::assertSent(fn ($request) => $request['action'] === 'ask' && str_contains($request['query'], '[[Datei:001tibi.jpg]]') && ! isset($request['pageids']));
});
test('follows smw continuation', function (): void {
    Http::fakeSequence()->push(['query' => ['results' => ['Datei:A.jpg' => $this->page('Datei:A.jpg', ['Aruula'])]], 'query-continue-offset' => 1])->push(['query' => ['results' => ['Datei:B.jpg' => $this->page('Datei:B.jpg', ['Drax'])]]]);
    $result = app(MaddraxikonApiClient::class)->coverKeywords(['Datei:A.jpg', 'Datei:B.jpg']);
    expect($result['Datei:B.jpg']['keywords'])->toBe(['Drax']);
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => str_contains($request['query'], '|offset=1'));
});
test('invalid or partial responses never become empty keywords', function (array $payload): void {
    Http::fake(['*' => Http::response($payload)]);
    $result = app(MaddraxikonApiClient::class)->coverKeywords(['Datei:A.jpg']);
    expect($result['Datei:A.jpg']['keywords'])->toBeNull()
        ->and($result['Datei:A.jpg']['error'])->not->toBeEmpty();
})->with('MaddraxikonCoverKeywordsTest.badPayloads');
test('repeated pages in a continuation invalidate the whole batch', function (): void {
    $page = ['query' => ['results' => ['Datei:A.jpg' => $this->page('Datei:A.jpg', ['Aruula'])]]];
    Http::fakeSequence()->push($page + ['query-continue-offset' => 1])->push($page);
    $result = app(MaddraxikonApiClient::class)->coverKeywords(['Datei:A.jpg', 'Datei:B.jpg']);
    foreach ($result as $source) {
        expect($source['keywords'])->toBeNull()
            ->and($source['error'])->not->toBeEmpty();
    }
    Http::assertSentCount(2);
});
test('aliases share one result and invalid keywords do not discard other files', function (): void {
    Http::fake(['*' => Http::response(['query' => ['results' => ['Datei:Alpha Beta.jpg' => $this->page('Datei:Alpha Beta.jpg', ['Aruula']), 'Datei:Broken.jpg' => $this->page('Datei:Broken.jpg', [null])]]])]);
    $result = app(MaddraxikonApiClient::class)->coverKeywords(['File:alpha_Beta.jpg', 'Datei:Alpha Beta.jpg', 'Datei:Broken.jpg']);
    expect($result['File:alpha_Beta.jpg']['keywords'])->toBe(['Aruula'])
        ->and($result['Datei:Alpha Beta.jpg'])->toBe($result['File:alpha_Beta.jpg'])
        ->and($result['Datei:Broken.jpg']['keywords'])->toBeNull();
    Http::assertSentCount(1);
});
test('unsafe titles are not sent and empty input has no network calls', function (): void {
    Http::fake();
    $client = app(MaddraxikonApiClient::class);
    expect($client->coverKeywords([]))->toBe([])
        ->and($client->coverKeywords(['File:foo]] OR [[+'])['File:foo]] OR [[+']['keywords'])->toBeNull();
    Http::assertNothingSent();
});
test('batches and deduplicates titles and reports http failures', function (): void {
    Http::fake(['*' => Http::response([], 503)]);
    $titles = array_map(fn ($i) => "Datei:{$i}.jpg", range(1, 26));
    $result = app(MaddraxikonApiClient::class)->coverKeywords([...$titles, 'File:1.jpg']);
    expect($result)->toHaveCount(27)
        ->and($result['File:1.jpg']['keywords'])->toBeNull();
    Http::assertSentCount(2);
});
