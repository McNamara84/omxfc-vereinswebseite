<?php

namespace Tests\Unit;

use App\Exceptions\MaddraxikonApiException;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaddraxikonApiClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'maddraxikon.base_url' => 'https://wiki.example.test',
            'maddraxikon.api_url' => 'https://wiki.example.test/api.php',
            'maddraxikon.allowed_namespaces' => [0, 10, 14],
            'maddraxikon.http.attempts' => 3,
            'maddraxikon.http.retry_delay_ms' => 0,
            'maddraxikon.http.retry_max_delay_ms' => 0,
            'maddraxikon.http.user_agent' => 'OMXFC-Test/1.0',
        ]);
    }

    public function test_recent_changes_follows_continuation_and_uses_a_fixed_window(): void
    {
        Http::fakeSequence()
            ->push([
                'continue' => [
                    'continue' => '-||',
                    'rccontinue' => '20260718120000|123',
                ],
                'query' => [
                    'recentchanges' => [[
                        'rcid' => 1,
                        'revid' => 101,
                        'title' => 'Erster Artikel',
                    ]],
                ],
            ])
            ->push([
                'batchcomplete' => true,
                'query' => [
                    'recentchanges' => [[
                        'rcid' => 2,
                        'revid' => 102,
                        'title' => 'Zweiter Artikel',
                    ]],
                ],
            ]);

        $from = CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC');
        $until = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');

        $changes = app(MaddraxikonApiClient::class)->recentChanges($from, $until);

        $this->assertSame([1, 2], array_column($changes, 'rcid'));

        $requests = collect(Http::recorded())
            ->map(fn (array $record): Request => $record[0])
            ->values();
        $this->assertCount(2, $requests);

        $first = $requests[0];
        parse_str(
            (string) parse_url($first->url(), PHP_URL_QUERY),
            $firstParameters,
        );
        $this->assertTrue($first->hasHeader('User-Agent', 'OMXFC-Test/1.0'));
        $this->assertStringContainsString('wiki.example.test/api.php', $first->url());
        $this->assertSame('2026-07-18T10:00:00Z', $firstParameters['rcstart'] ?? null);
        $this->assertSame('2026-07-18T12:00:00Z', $firstParameters['rcend'] ?? null);
        $this->assertSame('0|10|14', $firstParameters['rcnamespace'] ?? null);
        $this->assertSame(
            'title|ids|timestamp|user|userid|flags|sizes|tags',
            $firstParameters['rcprop'] ?? null,
        );
        $this->assertSame('2', $firstParameters['formatversion'] ?? null);

        $second = $requests[1];
        parse_str(
            (string) parse_url($second->url(), PHP_URL_QUERY),
            $secondParameters,
        );
        $this->assertSame('20260718120000|123', $secondParameters['rccontinue'] ?? null);
        $this->assertSame('-||', $secondParameters['continue'] ?? null);
    }

    public function test_user_contributions_batch_numeric_ids_and_follow_continuation(): void
    {
        config(['maddraxikon.sync.usercontribs_batch_size' => 2]);
        Http::fakeSequence()
            ->push([
                'continue' => [
                    'continue' => '-||',
                    'uccontinue' => '20260718103000|101',
                ],
                'query' => [
                    'usercontribs' => [['revid' => 101]],
                ],
            ])
            ->push([
                'query' => [
                    'usercontribs' => [['revid' => 102]],
                ],
            ])
            ->push([
                'query' => [
                    'usercontribs' => [['revid' => 103]],
                ],
            ]);

        $from = CarbonImmutable::parse('2026-06-01 10:00:00', 'UTC');
        $until = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $contributions = app(MaddraxikonApiClient::class)
            ->userContributions([11, 22, 33, 22, 0], $from, $until);

        $this->assertSame([101, 102, 103], array_column(
            $contributions,
            'revid'
        ));

        $requests = collect(Http::recorded())->map(function (array $record): array {
            parse_str($record[0]->url(), $parameters);

            return $parameters;
        })->values();

        $this->assertSame(
            ['11|22', '11|22', '33'],
            $requests->pluck('ucuserids')->all()
        );
        $this->assertSame('2026-06-01T10:00:00Z', $requests[0]['ucstart']);
        $this->assertSame('2026-07-18T12:00:00Z', $requests[0]['ucend']);
        $this->assertSame('newer', $requests[0]['ucdir']);
        $this->assertSame('0|10|14', $requests[0]['ucnamespace']);
        $this->assertSame(
            'ids|title|timestamp|flags|size|sizediff|tags',
            $requests[0]['ucprop']
        );
        $this->assertSame(
            '20260718103000|101',
            $requests[1]['uccontinue']
        );
    }

    public function test_api_url_must_use_https_and_match_the_configured_origin(): void
    {
        Http::fake();

        $unsafeConfigurations = [
            [
                'maddraxikon.base_url' => 'http://wiki.example.test',
                'maddraxikon.api_url' => 'http://wiki.example.test/api.php',
            ],
            [
                'maddraxikon.base_url' => 'https://wiki.example.test',
                'maddraxikon.api_url' => 'https://evil.example.test/api.php',
            ],
            [
                'maddraxikon.base_url' => 'https://wiki.example.test',
                'maddraxikon.api_url' => 'https://wiki.example.test:8443/api.php',
            ],
        ];

        foreach ($unsafeConfigurations as $configuration) {
            config($configuration);

            try {
                app(MaddraxikonApiClient::class)->recentChanges(
                    CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'),
                    CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'),
                );
                $this->fail('An unsafe API URL was accepted.');
            } catch (MaddraxikonApiException $exception) {
                $this->assertSame(
                    'Die Maddraxikon-API-URL ist nicht sicher konfiguriert.',
                    $exception->getMessage(),
                );
            }
        }

        Http::assertNothingSent();
    }

    public function test_api_redirect_is_not_followed(): void
    {
        Http::fake([
            'https://wiki.example.test/api.php*' => Http::response('', 302, [
                'Location' => 'https://evil.example.test/internal',
            ]),
            'https://evil.example.test/*' => Http::response([
                'query' => ['recentchanges' => []],
            ]),
        ]);

        try {
            app(MaddraxikonApiClient::class)->recentChanges(
                CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'),
                CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'),
            );
            $this->fail('A redirecting API endpoint was accepted.');
        } catch (MaddraxikonApiException $exception) {
            $this->assertSame(302, $exception->statusCode);
        }

        Http::assertSentCount(1);
        Http::assertNotSent(
            fn (Request $request): bool => str_starts_with(
                $request->url(),
                'https://evil.example.test/',
            ),
        );
    }

    public function test_repeated_continuation_marker_is_rejected(): void
    {
        $response = [
            'continue' => [
                'continue' => '-||',
                'rccontinue' => '20260718120000|123',
            ],
            'query' => ['recentchanges' => []],
        ];
        Http::fakeSequence()
            ->push($response)
            ->push($response);

        $this->expectException(MaddraxikonApiException::class);
        $this->expectExceptionMessage('wiederholte einen Fortsetzungsmarker');

        app(MaddraxikonApiClient::class)->recentChanges(
            CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'),
            CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'),
        );
    }

    public function test_maxlag_is_retried_without_losing_the_request(): void
    {
        Http::fakeSequence()
            ->push([
                'error' => [
                    'code' => 'maxlag',
                    'info' => 'Waiting for replicas',
                ],
            ])
            ->push([
                'query' => [
                    'recentchanges' => [],
                ],
            ]);

        $result = app(MaddraxikonApiClient::class)->recentChanges(
            CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'),
            CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'),
        );

        $this->assertSame([], $result);
        Http::assertSentCount(2);
    }

    public function test_transient_http_errors_are_retried_before_body_decoding(): void
    {
        Http::fakeSequence()
            ->push('<html>rate limited</html>', 429, ['Retry-After' => '0'])
            ->push('', 503)
            ->push([
                'query' => [
                    'recentchanges' => [],
                ],
            ]);

        $result = app(MaddraxikonApiClient::class)->recentChanges(
            CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'),
            CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'),
        );

        $this->assertSame([], $result);
        Http::assertSentCount(3);
    }

    public function test_connection_timeout_is_retried(): void
    {
        Http::fakeSequence()
            ->pushFailedConnection('cURL error 28: Operation timed out')
            ->push([
                'query' => [
                    'recentchanges' => [],
                ],
            ]);

        $result = app(MaddraxikonApiClient::class)->recentChanges(
            CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'),
            CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'),
        );

        $this->assertSame([], $result);
        Http::assertSentCount(2);
    }

    public function test_exhausted_connection_timeouts_raise_a_domain_exception(): void
    {
        Http::fakeSequence()
            ->pushFailedConnection('cURL error 28: Operation timed out')
            ->pushFailedConnection('cURL error 28: Operation timed out')
            ->pushFailedConnection('cURL error 28: Operation timed out');

        try {
            app(MaddraxikonApiClient::class)->recentChanges(
                CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'),
                CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'),
            );
            $this->fail('Expected MaddraxikonApiException was not thrown.');
        } catch (MaddraxikonApiException $exception) {
            $this->assertStringContainsString(
                'nach mehreren Versuchen nicht erreichbar',
                $exception->getMessage()
            );
        }

        Http::assertSentCount(3);
    }

    public function test_final_transient_http_error_reports_status_instead_of_invalid_json(): void
    {
        config(['maddraxikon.http.attempts' => 1]);

        Http::fake([
            '*' => Http::response('<html>maintenance</html>', 503),
        ]);

        try {
            app(MaddraxikonApiClient::class)->recentChanges(
                CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'),
                CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'),
            );
            $this->fail('Expected MaddraxikonApiException was not thrown.');
        } catch (MaddraxikonApiException $exception) {
            $this->assertSame(503, $exception->statusCode);
            $this->assertSame(
                'Die Maddraxikon-API antwortete mit HTTP 503.',
                $exception->getMessage()
            );
        }

        Http::assertSentCount(1);
    }

    public function test_invalid_json_raises_a_domain_exception(): void
    {
        Http::fake([
            '*' => Http::response('<html>kaputt</html>', 200),
        ]);

        $this->expectException(MaddraxikonApiException::class);
        $this->expectExceptionMessage('ungültiges JSON');

        app(MaddraxikonApiClient::class)->recentChanges(
            CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC'),
            CarbonImmutable::parse('2026-07-18 11:00:00', 'UTC'),
        );
    }

    public function test_revision_details_normalize_visible_hidden_reverted_and_missing_revisions(): void
    {
        Http::fake([
            '*' => Http::response([
                'query' => [
                    'pages' => [[
                        'pageid' => 77,
                        'ns' => 0,
                        'title' => 'Test',
                        'revisions' => [
                            [
                                'revid' => 10,
                                'userid' => 5,
                                'sha1' => 'visible-revision-sha1',
                                'size' => 700,
                                'tags' => [],
                            ],
                            [
                                'revid' => 11,
                                'userhidden' => true,
                                'sha1hidden' => true,
                                'texthidden' => true,
                                'suppressed' => true,
                                'tags' => ['mw-reverted'],
                            ],
                        ],
                    ]],
                ],
            ]),
        ]);

        $details = app(MaddraxikonApiClient::class)->revisionDetails([10, 11, 12]);

        $this->assertTrue($details[10]['exists']);
        $this->assertSame(5, $details[10]['user_id']);
        $this->assertSame(700, $details[10]['size']);
        $this->assertSame('visible-revision-sha1', $details[10]['sha1']);
        $this->assertFalse($details[10]['sha1_hidden']);
        $this->assertFalse($details[10]['text_hidden']);
        $this->assertTrue($details[11]['user_hidden']);
        $this->assertTrue($details[11]['suppressed']);
        $this->assertNull($details[11]['sha1']);
        $this->assertTrue($details[11]['sha1_hidden']);
        $this->assertTrue($details[11]['text_hidden']);
        $this->assertSame(['mw-reverted'], $details[11]['tags']);
        $this->assertFalse($details[12]['exists']);
        $this->assertNull($details[12]['sha1']);
        $this->assertFalse($details[12]['sha1_hidden']);
        $this->assertFalse($details[12]['text_hidden']);

        Http::assertSent(function (Request $request): bool {
            parse_str($request->url(), $parameters);

            return ($parameters['rvprop'] ?? null) === 'ids|timestamp|user|userid|size|sha1|flags|tags';
        });
    }

    public function test_page_details_normalize_size_redirect_and_missing_pages(): void
    {
        Http::fake([
            '*' => Http::response([
                'query' => [
                    'pages' => [
                        [
                            'pageid' => 80,
                            'ns' => 0,
                            'title' => 'Weiterleitung',
                            'redirect' => true,
                            'revisions' => [['revid' => 800, 'size' => 510]],
                        ],
                        [
                            'pageid' => 81,
                            'ns' => 10,
                            'title' => 'Vorlage:Info',
                            'revisions' => [['revid' => 801, 'size' => 42]],
                        ],
                    ],
                ],
            ]),
        ]);

        $details = app(MaddraxikonApiClient::class)->pageDetails([80, 81, 82]);

        $this->assertTrue($details[80]['exists']);
        $this->assertTrue($details[80]['redirect']);
        $this->assertSame(510, $details[80]['size']);
        $this->assertFalse($details[81]['redirect']);
        $this->assertSame(10, $details[81]['namespace_id']);
        $this->assertFalse($details[82]['exists']);
    }

    public function test_namespaces_use_localized_names_and_sort_by_id(): void
    {
        Http::fake([
            '*' => Http::response([
                'query' => [
                    'namespaces' => [
                        '10' => ['id' => 10, 'name' => 'Vorlage', 'canonical' => 'Template'],
                        '0' => ['id' => 0, 'name' => '', 'canonical' => ''],
                        '14' => ['id' => 14, 'name' => 'Kategorie', 'canonical' => 'Category'],
                    ],
                ],
            ]),
        ]);

        $this->assertSame([
            0 => '',
            10 => 'Vorlage',
            14 => 'Kategorie',
        ], app(MaddraxikonApiClient::class)->namespaces());
    }
}
