<?php

namespace Tests\Integration;

use App\Exceptions\MaddraxikonApiException;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use Illuminate\Support\Facades\Http;
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
