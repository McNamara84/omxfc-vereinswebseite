<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\MaddraxikonApiException;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

beforeEach(function (): void {
    config(['maddraxikon.base_url' => 'https://wiki.example.test', 'maddraxikon.api_url' => 'https://wiki.example.test/api.php', 'maddraxikon.allowed_namespaces' => [0, 10, 14], 'maddraxikon.http.attempts' => 3, 'maddraxikon.http.retry_delay_ms' => 0, 'maddraxikon.http.retry_max_delay_ms' => 0, 'maddraxikon.http.user_agent' => 'OMXFC-Test/1.0']);
});

test('recent changes follows continuation and uses a fixed window', function (): void {
    Http::fakeSequence()->push(['continue' => ['continue' => '-||', 'rccontinue' => '20260718120000|123'], 'query' => ['recentchanges' => [['rcid' => 1, 'revid' => 101, 'title' => 'Erster Artikel']]]])->push(['batchcomplete' => true, 'query' => ['recentchanges' => [['rcid' => 2, 'revid' => 102, 'title' => 'Zweiter Artikel']]]]);
    $from = CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC');
    $until = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $changes = app(MaddraxikonApiClient::class)->recentChanges($from, $until);
    expect(array_column($changes, 'rcid'))->toBe([1, 2]);
    $requests = collect(Http::recorded())->map(fn (array $record): Request => $record[0])->values();
    expect($requests)->toHaveCount(2);
    $first = $requests[0];
    parse_str((string) parse_url($first->url(), PHP_URL_QUERY), $firstParameters);
    expect($first->hasHeader('User-Agent', 'OMXFC-Test/1.0'))->toBeTrue()
        ->and($first->url())->toContain('wiki.example.test/api.php')
        ->and($firstParameters['rcstart'] ?? null)->toBe('2026-07-18T10:00:00Z')
        ->and($firstParameters['rcend'] ?? null)->toBe('2026-07-18T12:00:00Z')
        ->and($firstParameters['rcnamespace'] ?? null)->toBe('0|10|14')
        ->and($firstParameters['rcprop'] ?? null)->toBe('title|ids|timestamp|user|userid|flags|sizes|tags')
        ->and($firstParameters['formatversion'] ?? null)->toBe('2');
    $second = $requests[1];
    parse_str((string) parse_url($second->url(), PHP_URL_QUERY), $secondParameters);
    expect($secondParameters['rccontinue'] ?? null)->toBe('20260718120000|123')
        ->and($secondParameters['continue'] ?? null)->toBe('-||');
});

test('user contributions batch numeric ids and follow continuation', function (): void {
    config(['maddraxikon.sync.usercontribs_batch_size' => 2]);
    Http::fakeSequence()->push(['continue' => ['continue' => '-||', 'uccontinue' => '20260718103000|101'], 'query' => ['usercontribs' => [['revid' => 101]]]])->push(['query' => ['usercontribs' => [['revid' => 102]]]])->push(['query' => ['usercontribs' => [['revid' => 103]]]]);
    $from = CarbonImmutable::parse('2026-06-01 10:00:00', 'UTC');
    $until = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $contributions = app(MaddraxikonApiClient::class)->userContributions([11, 22, 33, 22, 0], $from, $until);
    expect(array_column($contributions, 'revid'))->toBe([101, 102, 103]);
    $requests = collect(Http::recorded())->map(function (array $record): array {
        parse_str($record[0]->url(), $parameters);

        return $parameters;
    })->values();
    expect($requests->pluck('ucuserids')->all())->toBe(['11|22', '11|22', '33'])
        ->and($requests[0]['ucstart'])->toBe('2026-06-01T10:00:00Z')
        ->and($requests[0]['ucend'])->toBe('2026-07-18T12:00:00Z')
        ->and($requests[0]['ucdir'])->toBe('newer')
        ->and($requests[0]['ucnamespace'])->toBe('0|10|14')
        ->and($requests[0]['ucprop'])->toBe('ids|title|timestamp|flags|size|sizediff|tags')
        ->and($requests[1]['uccontinue'])->toBe('20260718103000|101');
});

test('api url must use https and match the configured origin', function (): void {
    Http::fake();
    $unsafeConfigurations = [['maddraxikon.base_url' => 'http://wiki.example.test', 'maddraxikon.api_url' => 'http://wiki.example.test/api.php'], ['maddraxikon.base_url' => 'https://wiki.example.test', 'maddraxikon.api_url' => 'https://evil.example.test/api.php'], ['maddraxikon.base_url' => 'https://wiki.example.test', 'maddraxikon.api_url' => 'https://wiki.example.test:8443/api.php']];
    foreach ($unsafeConfigurations as $configuration) {
        config($configuration);
        try {
            app(MaddraxikonApiClient::class)->recentChanges(CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'), CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'));
            $this->fail('An unsafe API URL was accepted.');
        } catch (MaddraxikonApiException $exception) {
            expect($exception->getMessage())->toBe('Die Maddraxikon-API-URL ist nicht sicher konfiguriert.');
        }
    }
    Http::assertNothingSent();
});

test('api redirect is not followed', function (): void {
    Http::fake(['https://wiki.example.test/api.php*' => Http::response('', 302, ['Location' => 'https://evil.example.test/internal']), 'https://evil.example.test/*' => Http::response(['query' => ['recentchanges' => []]])]);
    try {
        app(MaddraxikonApiClient::class)->recentChanges(CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'), CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'));
        $this->fail('A redirecting API endpoint was accepted.');
    } catch (MaddraxikonApiException $exception) {
        expect($exception->statusCode)->toBe(302);
    }
    Http::assertSentCount(1);
    Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://evil.example.test/'));
});

test('repeated continuation marker is rejected', function (): void {
    $response = ['continue' => ['continue' => '-||', 'rccontinue' => '20260718120000|123'], 'query' => ['recentchanges' => []]];
    Http::fakeSequence()->push($response)->push($response);
    expect(fn () => app(MaddraxikonApiClient::class)->recentChanges(CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'), CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC')))->toThrow(MaddraxikonApiException::class, 'wiederholte einen Fortsetzungsmarker');
});

test('maxlag is retried without losing the request', function (): void {
    Http::fakeSequence()->push(['error' => ['code' => 'maxlag', 'info' => 'Waiting for replicas']])->push(['query' => ['recentchanges' => []]]);
    $result = app(MaddraxikonApiClient::class)->recentChanges(CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'), CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'));
    expect($result)->toBe([]);
    Http::assertSentCount(2);
});

test('transient http errors are retried before body decoding', function (): void {
    Http::fakeSequence()->push('<html>rate limited</html>', 429, ['Retry-After' => '0'])->push('', 503)->push(['query' => ['recentchanges' => []]]);
    $result = app(MaddraxikonApiClient::class)->recentChanges(CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'), CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'));
    expect($result)->toBe([]);
    Http::assertSentCount(3);
});

test('connection timeout is retried', function (): void {
    Http::fakeSequence()->pushFailedConnection('cURL error 28: Operation timed out')->push(['query' => ['recentchanges' => []]]);
    $result = app(MaddraxikonApiClient::class)->recentChanges(CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'), CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'));
    expect($result)->toBe([]);
    Http::assertSentCount(2);
});

test('exhausted connection timeouts raise a domain exception', function (): void {
    Http::fakeSequence()->pushFailedConnection('cURL error 28: Operation timed out')->pushFailedConnection('cURL error 28: Operation timed out')->pushFailedConnection('cURL error 28: Operation timed out');
    try {
        app(MaddraxikonApiClient::class)->recentChanges(CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'), CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'));
        $this->fail('Expected MaddraxikonApiException was not thrown.');
    } catch (MaddraxikonApiException $exception) {
        expect($exception->getMessage())->toContain('nach mehreren Versuchen nicht erreichbar');
    }
    Http::assertSentCount(3);
});

test('final transient http error reports status instead of invalid json', function (): void {
    config(['maddraxikon.http.attempts' => 1]);
    Http::fake(['*' => Http::response('<html>maintenance</html>', 503)]);
    try {
        app(MaddraxikonApiClient::class)->recentChanges(CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'), CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'));
        $this->fail('Expected MaddraxikonApiException was not thrown.');
    } catch (MaddraxikonApiException $exception) {
        expect($exception->statusCode)->toBe(503)
            ->and($exception->getMessage())->toBe('Die Maddraxikon-API antwortete mit HTTP 503.');
    }
    Http::assertSentCount(1);
});

test('invalid json raises a domain exception', function (): void {
    Http::fake(['*' => Http::response('<html>kaputt</html>', 200)]);
    expect(fn () => app(MaddraxikonApiClient::class)->recentChanges(CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'), CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC')))->toThrow(MaddraxikonApiException::class, 'ungültiges JSON');
});

test('revision details normalize visible hidden reverted and missing revisions', function (): void {
    Http::fake(['*' => Http::response(['query' => ['pages' => [['pageid' => 77, 'ns' => 0, 'title' => 'Test', 'revisions' => [['revid' => 10, 'userid' => 5, 'userhidden' => false, 'suppressed' => false, 'sha1' => 'visible-revision-sha1', 'sha1hidden' => false, 'texthidden' => false, 'size' => 700, 'tags' => []], ['revid' => 11, 'userhidden' => true, 'sha1hidden' => true, 'texthidden' => true, 'suppressed' => true, 'tags' => ['mw-reverted']]]]]]])]);
    $details = app(MaddraxikonApiClient::class)->revisionDetails([10, 11, 12]);
    expect($details[10]['exists'])->toBeTrue()
        ->and($details[10]['user_id'])->toBe(5)
        ->and($details[10]['size'])->toBe(700)
        ->and($details[10]['sha1'])->toBe('visible-revision-sha1')
        ->and($details[10]['sha1_hidden'])->toBeFalse()
        ->and($details[10]['text_hidden'])->toBeFalse()
        ->and($details[11]['user_hidden'])->toBeTrue()
        ->and($details[11]['suppressed'])->toBeTrue()
        ->and($details[11]['sha1'])->toBeNull()
        ->and($details[11]['sha1_hidden'])->toBeTrue()
        ->and($details[11]['text_hidden'])->toBeTrue()
        ->and($details[11]['tags'])->toBe(['mw-reverted'])
        ->and($details[12]['exists'])->toBeFalse()
        ->and($details[12]['sha1'])->toBeNull()
        ->and($details[12]['sha1_hidden'])->toBeFalse()
        ->and($details[12]['text_hidden'])->toBeFalse();
    Http::assertSent(function (Request $request): bool {
        parse_str($request->url(), $parameters);

        return ($parameters['rvprop'] ?? null) === 'ids|timestamp|user|userid|size|sha1|flags|tags';
    });
});

test('page details normalize size redirect and missing pages', function (): void {
    Http::fake(['*' => Http::response(['query' => ['pages' => [['pageid' => 80, 'ns' => 0, 'title' => 'Weiterleitung', 'missing' => false, 'redirect' => true, 'length' => 510], ['pageid' => 81, 'ns' => 10, 'title' => 'Vorlage:Info', 'missing' => false, 'redirect' => false, 'length' => 42]]]])]);
    $details = app(MaddraxikonApiClient::class)->pageDetails([80, 81, 82]);
    expect($details[80]['exists'])->toBeTrue()
        ->and($details[80]['redirect'])->toBeTrue()
        ->and($details[80]['size'])->toBe(510)
        ->and($details[81]['redirect'])->toBeFalse()
        ->and($details[81]['namespace_id'])->toBe(10)
        ->and($details[82]['exists'])->toBeFalse();
    Http::assertSent(function (Request $request): bool {
        parse_str($request->url(), $parameters);

        return ($parameters['prop'] ?? null) === 'info' && ($parameters['pageids'] ?? null) === '80|81|82' && ! array_key_exists('rvlimit', $parameters) && ! array_key_exists('rvprop', $parameters);
    });
});

test('namespaces use localized names and sort by id', function (): void {
    Http::fake(['*' => Http::response(['query' => ['namespaces' => ['10' => ['id' => 10, 'name' => 'Vorlage', 'canonical' => 'Template'], '0' => ['id' => 0, 'name' => '', 'canonical' => ''], '14' => ['id' => 14, 'name' => 'Kategorie', 'canonical' => 'Category']]]])]);
    expect(app(MaddraxikonApiClient::class)->namespaces())->toBe([0 => '', 10 => 'Vorlage', 14 => 'Kategorie']);
});

test('cover images resolve page image file metadata and missing pages', function (): void {
    Http::fakeSequence()->push(['query' => ['pages' => [['pageid' => 10, 'pageimage' => 'Cover_10.jpg', 'original' => ['source' => 'https://wiki.example.test/original.jpg', 'width' => 1400, 'height' => 2000]], ['pageid' => 11]]]])->push(['query' => ['pages' => [['pageid' => 99, 'title' => 'Datei:Cover_10.jpg', 'imageinfo' => [['thumburl' => 'https://wiki.example.test/thumb/cover.jpg', 'descriptionurl' => 'https://wiki.example.test/wiki/Datei:Cover_10.jpg', 'thumbwidth' => 720, 'thumbheight' => 1029, 'width' => 1400, 'height' => 2000, 'sha1' => str_repeat('a', 40), 'mime' => 'image/jpeg', 'extmetadata' => ['Artist' => ['value' => '<b>Jane Artist</b>'], 'Credit' => ['value' => 'Bastei &amp; Test'], 'LicenseShortName' => ['value' => 'CC BY-SA 4.0'], 'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0/']]]]]]]]);
    $details = app(MaddraxikonApiClient::class)->coverImages([10, 11, 10, 0]);
    expect(array_keys($details))->toBe([10, 11])
        ->and($details[10]['exists'])->toBeTrue()
        ->and($details[10]['file_title'])->toBe('Datei:Cover_10.jpg')
        ->and($details[10]['url'])->toBe('https://wiki.example.test/thumb/cover.jpg')
        ->and($details[10]['sha1'])->toBe(str_repeat('a', 40))
        ->and($details[10]['width'])->toBe(720)
        ->and($details[10]['height'])->toBe(1029)
        ->and($details[10]['artist'])->toBe('Jane Artist')
        ->and($details[10]['credit'])->toBe('Bastei & Test')
        ->and($details[10]['license'])->toBe('CC BY-SA 4.0')
        ->and($details[11]['exists'])->toBeFalse()
        ->and($details[11]['url'])->toBeNull();
    $requests = collect(Http::recorded())->map(function (array $record): array {
        parse_str($record[0]->url(), $parameters);

        return $parameters;
    });
    expect($requests[0]['prop'])->toBe('pageimages')
        ->and($requests[0]['pageids'])->toBe('10|11')
        ->and($requests[1]['prop'])->toBe('imageinfo')
        ->and($requests[1]['titles'])->toBe('File:Cover_10.jpg')
        ->and($requests[1]['iiurlwidth'])->toBe('720');
});

test('cover metadata drops non https public links', function (): void {
    Http::fakeSequence()->push(['query' => ['pages' => [['pageid' => 20, 'pageimage' => 'Unsafe.jpg']]]])->push(['query' => ['pages' => [['title' => 'File:Unsafe.jpg', 'imageinfo' => [['url' => 'https://wiki.example.test/unsafe.jpg', 'descriptionurl' => 'javascript:alert(1)', 'sha1' => 'abc', 'mime' => 'image/jpeg', 'width' => 10, 'height' => 20, 'extmetadata' => ['LicenseUrl' => ['value' => 'http://license.example.test']]]]]]]]);
    $detail = app(MaddraxikonApiClient::class)->coverImages([20])[20];
    expect($detail['exists'])->toBeTrue()
        ->and($detail['description_url'])->toBeNull()
        ->and($detail['license_url'])->toBeNull();
});

test('cover images avoid api calls for an empty id list', function (): void {
    Http::fake();
    expect(app(MaddraxikonApiClient::class)->coverImages([]))->toBe([]);
    Http::assertNothingSent();
});
