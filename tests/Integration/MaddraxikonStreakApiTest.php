<?php

namespace Tests\Integration;

use App\Exceptions\MaddraxikonApiException;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaddraxikonStreakApiTest extends TestCase
{
    public function test_omitted_revision_is_a_technical_error_not_a_reversal(): void
    {
        Http::fake(['*' => Http::response(['query' => ['pages' => []]])]);
        $this->expectException(MaddraxikonApiException::class);
        app(MaddraxikonApiClient::class)->streakRevisionDetails([42]);
    }

    public function test_explicitly_missing_revision_is_authoritative_even_without_pages(): void
    {
        Http::fake(['*' => Http::response(['query' => ['badrevids' => [['revid' => 42, 'missing' => true]]]])]);
        $this->assertFalse(app(MaddraxikonApiClient::class)->streakRevisionDetails([42])[42]['exists']);
    }

    #[DataProvider('mixedRevisionBatches')]
    public function test_mixed_batches_preserve_visible_and_explicitly_missing_revisions(string $method, bool $keyed): void
    {
        Http::fake(function (Request $request) use ($keyed) {
            $ids = array_map('intval', explode('|', $request['revids']));
            $missing = [];
            $revisions = [];
            foreach ($ids as $id) {
                if (in_array($id, [2, 51], true)) {
                    $missing[$id] = ['revid' => $id, 'missing' => true];
                } else {
                    $revisions[] = ['revid' => $id, 'userid' => 7, 'tags' => ['manual-tag']];
                }
            }

            return Http::response(['query' => [
                'badrevids' => $keyed ? $missing : array_values($missing),
                'pages' => [['pageid' => 10, 'ns' => 0, 'revisions' => $revisions]],
            ]]);
        });

        $details = app(MaddraxikonApiClient::class)->{$method}(range(1, 52));

        $this->assertSame(range(1, 52), array_keys($details));
        foreach ($details as $id => $revision) {
            $this->assertSame($id, $revision['revision_id']);
            $this->assertSame(! in_array($id, [2, 51], true), $revision['exists']);
            if ($revision['exists']) {
                $this->assertSame(10, $revision['page_id']);
                $this->assertSame(7, $revision['user_id']);
                $this->assertSame(['manual-tag'], $revision['tags']);
            }
        }
        Http::assertSentCount(2);
    }

    public static function mixedRevisionBatches(): array
    {
        return [
            'streaks with list' => ['streakRevisionDetails', false],
            'streaks with keyed entries' => ['streakRevisionDetails', true],
            'regular rewards with list' => ['revisionDetails', false],
            'regular rewards with keyed entries' => ['revisionDetails', true],
        ];
    }

    public function test_explicit_missing_entries_do_not_hide_an_omitted_revision_in_a_mixed_batch(): void
    {
        Http::fake(['*' => Http::response(['query' => [
            'badrevids' => [['revid' => 42, 'missing' => true]],
            'pages' => [['pageid' => 10, 'ns' => 0, 'revisions' => [['revid' => 43, 'userid' => 7, 'tags' => []]]]],
        ]])]);
        $this->expectException(MaddraxikonApiException::class);
        $this->expectExceptionMessage('Eine angeforderte Revision fehlt ohne ausdrücklichen Missing-Nachweis');
        app(MaddraxikonApiClient::class)->streakRevisionDetails([42, 43, 44]);
    }

    public function test_visible_revision_and_explicitly_deleted_page_are_normalized(): void
    {
        Http::fakeSequence()->push(['query' => ['pages' => [['pageid' => 10, 'ns' => 0, 'revisions' => [['revid' => 42, 'userid' => 7, 'tags' => []]]]]]])
            ->push(['query' => ['pages' => [['pageid' => 10, 'missing' => true]]]]);
        $this->assertSame(7, app(MaddraxikonApiClient::class)->streakRevisionDetails([42])[42]['user_id']);
        $this->assertFalse(app(MaddraxikonApiClient::class)->streakPageDetails([10])[10]['exists']);
    }

    public function test_omitted_page_does_not_mean_deleted_page(): void
    {
        Http::fake(['*' => Http::response(['query' => ['pages' => []]])]);
        $this->expectException(MaddraxikonApiException::class);
        app(MaddraxikonApiClient::class)->streakPageDetails([10]);
    }

    public function test_empty_id_batches_make_no_requests(): void
    {
        Http::fake();
        $this->assertSame([], app(MaddraxikonApiClient::class)->streakPageDetails([]));
        $this->assertSame([], app(MaddraxikonApiClient::class)->streakRevisionDetails([]));
        Http::assertNothingSent();
    }

    public function test_missing_tags_cannot_make_a_reverted_revision_eligible(): void
    {
        Http::fake(['*' => Http::response(['query' => ['pages' => [['pageid' => 10, 'ns' => 0, 'revisions' => [['revid' => 42, 'userid' => 7]]]]]])]);
        $this->expectException(MaddraxikonApiException::class);
        app(MaddraxikonApiClient::class)->streakRevisionDetails([42]);
    }
}
