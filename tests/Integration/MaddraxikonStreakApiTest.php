<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\MaddraxikonApiException;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class);
dataset('MaddraxikonStreakApiTest.mixedRevisionBatches', static fn (): array => ['streaks with list' => ['streakRevisionDetails', false], 'streaks with keyed entries' => ['streakRevisionDetails', true], 'regular rewards with list' => ['revisionDetails', false], 'regular rewards with keyed entries' => ['revisionDetails', true]]);
test('omitted revision is a technical error not a reversal', function (): void {
    Http::fake(['*' => Http::response(['query' => ['pages' => []]])]);
    expect(fn () => app(MaddraxikonApiClient::class)->streakRevisionDetails([42]))->toThrow(MaddraxikonApiException::class);
});
test('explicitly missing revision is authoritative even without pages', function (): void {
    Http::fake(['*' => Http::response(['query' => ['badrevids' => [['revid' => 42, 'missing' => true]]]])]);
    expect(app(MaddraxikonApiClient::class)->streakRevisionDetails([42])[42]['exists'])->toBeFalse();
});
test('mixed batches preserve visible and explicitly missing revisions', function (string $method, bool $keyed): void {
    Http::fake(function (Request $request) use ($keyed) {
        $ids = array_map(intval(...), explode('|', $request['revids']));
        $missing = [];
        $revisions = [];
        foreach ($ids as $id) {
            if (in_array($id, [2, 51], true)) {
                $missing[$id] = ['revid' => $id, 'missing' => true];
            } else {
                $revisions[] = ['revid' => $id, 'userid' => 7, 'tags' => ['manual-tag']];
            }
        }

        return Http::response(['query' => ['badrevids' => $keyed ? $missing : array_values($missing), 'pages' => [['pageid' => 10, 'ns' => 0, 'revisions' => $revisions]]]]);
    });
    $details = app(MaddraxikonApiClient::class)->{$method}(range(1, 52));
    expect(array_keys($details))->toBe(range(1, 52));
    foreach ($details as $id => $revision) {
        expect($revision['revision_id'])->toBe($id)
            ->and($revision['exists'])->toBe(! in_array($id, [2, 51], true));
        if ($revision['exists']) {
            expect($revision['page_id'])->toBe(10)
                ->and($revision['user_id'])->toBe(7)
                ->and($revision['tags'])->toBe(['manual-tag']);
        }
    }
    Http::assertSentCount(2);
})->with('MaddraxikonStreakApiTest.mixedRevisionBatches');
test('explicit missing entries do not hide an omitted revision in a mixed batch', function (): void {
    Http::fake(['*' => Http::response(['query' => ['badrevids' => [['revid' => 42, 'missing' => true]], 'pages' => [['pageid' => 10, 'ns' => 0, 'revisions' => [['revid' => 43, 'userid' => 7, 'tags' => []]]]]]])]);
    expect(fn () => app(MaddraxikonApiClient::class)->streakRevisionDetails([42, 43, 44]))->toThrow(MaddraxikonApiException::class, 'Eine angeforderte Revision fehlt ohne ausdrücklichen Missing-Nachweis');
});
test('visible revision and explicitly deleted page are normalized', function (): void {
    Http::fakeSequence()->push(['query' => ['pages' => [['pageid' => 10, 'ns' => 0, 'revisions' => [['revid' => 42, 'userid' => 7, 'tags' => []]]]]]])->push(['query' => ['pages' => [['pageid' => 10, 'missing' => true]]]]);
    expect(app(MaddraxikonApiClient::class)->streakRevisionDetails([42])[42]['user_id'])->toBe(7)
        ->and(app(MaddraxikonApiClient::class)->streakPageDetails([10])[10]['exists'])->toBeFalse();
});
test('omitted page does not mean deleted page', function (): void {
    Http::fake(['*' => Http::response(['query' => ['pages' => []]])]);
    expect(fn () => app(MaddraxikonApiClient::class)->streakPageDetails([10]))->toThrow(MaddraxikonApiException::class);
});
test('empty id batches make no requests', function (): void {
    Http::fake();
    expect(app(MaddraxikonApiClient::class)->streakPageDetails([]))->toBe([])
        ->and(app(MaddraxikonApiClient::class)->streakRevisionDetails([]))->toBe([]);
    Http::assertNothingSent();
});
test('missing tags cannot make a reverted revision eligible', function (): void {
    Http::fake(['*' => Http::response(['query' => ['pages' => [['pageid' => 10, 'ns' => 0, 'revisions' => [['revid' => 42, 'userid' => 7]]]]]])]);
    expect(fn () => app(MaddraxikonApiClient::class)->streakRevisionDetails([42]))->toThrow(MaddraxikonApiException::class);
});
