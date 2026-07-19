<?php

namespace Tests\Feature;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Enums\Role;
use App\Mail\MaddraxikonAccountLinked;
use App\Models\Activity;
use App\Models\MaddraxikonAccountLink;
use App\Models\Team;
use App\Models\User;
use App\Services\Maddraxikon\Exceptions\InvalidOAuthAttemptException;
use App\Services\Maddraxikon\OAuthAttemptStore;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MaddraxikonOAuthTest extends TestCase
{
    use RefreshDatabase;

    private const AUTHORIZE_URL = 'https://de.maddraxikon.com/rest.php/oauth2/authorize';

    private const TOKEN_URL = 'https://de.maddraxikon.com/rest.php/oauth2/access_token';

    private const PROFILE_URL = 'https://de.maddraxikon.com/rest.php/oauth2/resource/profile';

    private const API_URL = 'https://de.maddraxikon.com/api.php';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://maddrax-fanclub.de',
            'maddraxikon.features.linking_enabled' => true,
            'maddraxikon.base_url' => 'https://de.maddraxikon.com',
            'maddraxikon.api_url' => self::API_URL,
            'maddraxikon.oauth.authorize_url' => self::AUTHORIZE_URL,
            'maddraxikon.oauth.token_url' => self::TOKEN_URL,
            'maddraxikon.oauth.profile_url' => self::PROFILE_URL,
            'maddraxikon.wiki_key' => 'maddraxikon-de',
            'maddraxikon.consent_version' => '2026-07-18',
            'services.maddraxikon.client_id' => 'client-id',
            'services.maddraxikon.client_secret' => 'client-secret',
            'services.maddraxikon.redirect_uri' => 'https://maddrax-fanclub.de/oauth/maddraxikon/callback',
            'services.maddraxikon.scope' => 'mwoauth-authonly',
        ]);

        Mail::fake();
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_start_uses_fixed_callback_state_and_pkce_s256(): void
    {
        $member = $this->createMember();

        $response = $this->actingAs($member)->post(route('maddraxikon.oauth.start'), [
            'consent' => '1',
        ]);

        $response->assertRedirect();
        $query = $this->redirectQuery($response->headers->get('Location'));

        $this->assertStringStartsWith(self::AUTHORIZE_URL.'?', $response->headers->get('Location'));
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('client-id', $query['client_id']);
        $this->assertSame('https://maddrax-fanclub.de/oauth/maddraxikon/callback', $query['redirect_uri']);
        $this->assertSame('mwoauth-authonly', $query['scope']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $query['state']);
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $query['code_challenge']);
        $this->assertArrayNotHasKey('email', $query);
    }

    public function test_expired_login_session_still_gets_secure_callback_headers(): void
    {
        $response = $this->get(route('maddraxikon.oauth.callback', [
            'state' => str_repeat('a', 43),
            'code' => 'must-not-leak',
        ]));

        $response->assertRedirect(route('login'));
        $this->assertSecureCallbackHeaders($response);
        $this->assertDatabaseCount('maddraxikon_account_links', 0);
        Http::assertNothingSent();
    }

    public function test_rate_limited_callback_still_gets_secure_headers(): void
    {
        $response = null;

        foreach (range(1, 11) as $attempt) {
            $response = $this->get(route('maddraxikon.oauth.callback', [
                'state' => str_repeat('b', 43),
                'code' => 'must-not-leak-'.$attempt,
            ]));
        }

        $this->assertInstanceOf(TestResponse::class, $response);
        $response->assertTooManyRequests();
        $this->assertSecureCallbackHeaders($response);
        Http::assertNothingSent();
    }

    public function test_valid_callback_links_account_and_queues_information_mail(): void
    {
        $member = $this->createMember(attributes: ['email' => 'member@example.com']);
        $stateAndChallenge = $this->beginLink($member);
        $this->fakeSuccessfulIdentity();

        $response = $this->get(route('maddraxikon.oauth.callback', [
            'state' => $stateAndChallenge['state'],
            'code' => 'single-use-code',
        ]));

        $response
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('maddraxikon_status');
        $this->assertSecureCallbackHeaders($response);

        $link = MaddraxikonAccountLink::query()->where('user_id', $member->id)->firstOrFail();

        $this->assertSame('42', $link->oauth_subject);
        $this->assertSame(42, $link->wiki_user_id);
        $this->assertSame('Wiki Mitglied', $link->wiki_username);
        $this->assertSame(MaddraxikonAccountLinkStatus::Active, $link->status);
        $this->assertSame('oauth2', $link->verification_method);
        $this->assertSame('2026-07-18', $link->consent_version);
        $this->assertNull($link->disconnected_at);
        $this->assertDatabaseHas('activities', [
            'user_id' => $member->id,
            'subject_type' => MaddraxikonAccountLink::class,
            'subject_id' => $link->id,
            'action' => Activity::ACTION_MADDRAXIKON_ACCOUNT_LINKED,
        ]);

        Mail::assertQueued(MaddraxikonAccountLinked::class, function (MaddraxikonAccountLinked $mail): bool {
            return $mail->hasTo('member@example.com')
                && $mail->wikiUsername === 'Wiki Mitglied';
        });

        $expectedChallenge = $stateAndChallenge['code_challenge'];

        Http::assertSent(function (ClientRequest $request) use ($expectedChallenge): bool {
            if ($request->url() !== self::TOKEN_URL) {
                return false;
            }

            $verifier = $request['code_verifier'];
            $calculated = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

            return $request->method() === 'POST'
                && $request['code'] === 'single-use-code'
                && $request['client_secret'] === 'client-secret'
                && $request['redirect_uri'] === 'https://maddrax-fanclub.de/oauth/maddraxikon/callback'
                && hash_equals($expectedChallenge, $calculated);
        });

        Http::assertSent(function (ClientRequest $request): bool {
            return str_starts_with($request->url(), self::API_URL.'?')
                && $request['action'] === 'query'
                && $request['list'] === 'users'
                && $request['ususers'] === 'Wiki Mitglied'
                && ! $request->hasHeader('Authorization');
        });

        $databaseValues = DB::table('maddraxikon_account_links')->get()->toJson();
        $this->assertStringNotContainsString('access-secret', $databaseValues);
        $this->assertStringNotContainsString('refresh-secret', $databaseValues);
    }

    public function test_callback_state_is_single_use(): void
    {
        $member = $this->createMember();
        $attempt = $this->beginLink($member);
        $this->fakeSuccessfulIdentity();

        $this->get(route('maddraxikon.oauth.callback', [
            'state' => $attempt['state'],
            'code' => 'first-code',
        ]))->assertSessionHas('maddraxikon_status');

        $this->get(route('maddraxikon.oauth.callback', [
            'state' => $attempt['state'],
            'code' => 'second-code',
        ]))->assertSessionHas('maddraxikon_error');

        Http::assertSentCount(3);
        Mail::assertQueued(MaddraxikonAccountLinked::class, 1);
        $this->assertDatabaseCount('maddraxikon_account_links', 1);
    }

    public function test_reauth_of_active_link_does_not_duplicate_link_activity(): void
    {
        $member = $this->createMember();
        $this->fakeSuccessfulIdentity();

        foreach (['first-code', 'reauth-code'] as $code) {
            $attempt = $this->beginLink($member);

            $this->get(route('maddraxikon.oauth.callback', [
                'state' => $attempt['state'],
                'code' => $code,
            ]))->assertSessionHas('maddraxikon_status');
        }

        $link = MaddraxikonAccountLink::query()
            ->where('user_id', $member->id)
            ->sole();

        $this->assertSame(
            1,
            Activity::query()
                ->where('user_id', $member->id)
                ->where('subject_type', MaddraxikonAccountLink::class)
                ->where('subject_id', $link->id)
                ->where('action', Activity::ACTION_MADDRAXIKON_ACCOUNT_LINKED)
                ->count()
        );
    }

    public function test_callback_is_bound_to_original_laravel_user(): void
    {
        $member = $this->createMember();
        $otherMember = $this->createMember();
        $attempt = $this->beginLink($member);
        $this->actingAs($otherMember);

        $this->get(route('maddraxikon.oauth.callback', [
            'state' => $attempt['state'],
            'code' => 'code',
        ]))->assertSessionHas('maddraxikon_error');

        $this->actingAs($member)
            ->get(route('maddraxikon.oauth.callback', [
                'state' => $attempt['state'],
                'code' => 'code',
            ]))
            ->assertSessionHas('maddraxikon_error');

        Http::assertNothingSent();
        $this->assertDatabaseCount('maddraxikon_account_links', 0);
    }

    public function test_provider_denial_consumes_attempt_without_token_request(): void
    {
        $member = $this->createMember();
        $attempt = $this->beginLink($member);

        $response = $this->get(route('maddraxikon.oauth.callback', [
            'state' => $attempt['state'],
            'error' => 'access_denied',
            'error_description' => 'untrusted provider text',
        ]));

        $response->assertRedirect(route('profile.show'))
            ->assertSessionHas('maddraxikon_error', 'Die Verknüpfung wurde im Maddraxikon nicht bestätigt.');
        $this->assertSecureCallbackHeaders($response);

        Http::assertNothingSent();
        $this->assertDatabaseCount('maddraxikon_account_links', 0);
    }

    public function test_callback_accepts_opaque_subject_and_resolves_local_id_from_confirmed_username(): void
    {
        $member = $this->createMember();
        $attempt = $this->beginLink($member);
        $this->fakeSuccessfulIdentity(
            subject: 'opaque:wiki-identity:42',
            apiUserId: 43,
        );

        $this->get(route('maddraxikon.oauth.callback', [
            'state' => $attempt['state'],
            'code' => 'code',
        ]))->assertSessionHas('maddraxikon_status');

        $this->assertDatabaseHas('maddraxikon_account_links', [
            'user_id' => $member->id,
            'oauth_subject' => 'opaque:wiki-identity:42',
            'wiki_user_id' => 43,
            'wiki_username' => 'Wiki Mitglied',
        ]);
    }

    public function test_callback_rejects_blocked_wiki_account(): void
    {
        $member = $this->createMember();
        $attempt = $this->beginLink($member);
        $this->fakeSuccessfulIdentity(apiExtras: ['blockedby' => 'Admin', 'blockid' => 5]);

        $this->get(route('maddraxikon.oauth.callback', [
            'state' => $attempt['state'],
            'code' => 'code',
        ]))->assertSessionHas('maddraxikon_error');

        $this->assertDatabaseCount('maddraxikon_account_links', 0);
    }

    public function test_callback_rejects_malformed_token_response(): void
    {
        $member = $this->createMember();
        $attempt = $this->beginLink($member);
        Http::fake([
            self::TOKEN_URL => Http::response(['refresh_token' => 'refresh-secret'], 200),
        ]);

        $response = $this->get(route('maddraxikon.oauth.callback', [
            'state' => $attempt['state'],
            'code' => 'code',
        ]));

        $response->assertSessionHas('maddraxikon_error');
        $this->assertSecureCallbackHeaders($response);

        $this->assertDatabaseCount('maddraxikon_account_links', 0);
    }

    public function test_callback_rejects_non_string_empty_control_or_oversized_subjects(): void
    {
        $member = $this->createMember();
        $invalidSubjects = [
            42,
            '',
            "control\0subject",
            str_repeat('x', 192),
        ];

        foreach ($invalidSubjects as $index => $invalidSubject) {
            $attempt = $this->beginLink($member);
            $this->fakeSuccessfulIdentity(subject: $invalidSubject);

            $this->get(route('maddraxikon.oauth.callback', [
                'state' => $attempt['state'],
                'code' => "code-{$index}",
            ]))->assertSessionHas('maddraxikon_error');
        }

        $this->assertDatabaseCount('maddraxikon_account_links', 0);
        Mail::assertNothingQueued();
    }

    public function test_historical_wiki_identity_cannot_be_claimed_by_another_member(): void
    {
        $owner = $this->createMember();
        $claimant = $this->createMember();

        MaddraxikonAccountLink::factory()->disconnected()->for($owner)->create([
            'oauth_subject' => '42',
            'wiki_user_id' => 42,
            'wiki_username' => 'Wiki Mitglied',
        ]);

        $attempt = $this->beginLink($claimant);
        $this->fakeSuccessfulIdentity();

        $this->get(route('maddraxikon.oauth.callback', [
            'state' => $attempt['state'],
            'code' => 'code',
        ]))->assertSessionHas('maddraxikon_error');

        $this->assertDatabaseMissing('maddraxikon_account_links', [
            'user_id' => $claimant->id,
        ]);
        Mail::assertNothingQueued();
    }

    public function test_member_cannot_switch_to_a_different_wiki_identity_in_self_service(): void
    {
        $member = $this->createMember();

        MaddraxikonAccountLink::factory()->disconnected()->for($member)->create([
            'oauth_subject' => '99',
            'wiki_user_id' => 99,
            'wiki_username' => 'Altes Wiki-Konto',
        ]);

        $attempt = $this->beginLink($member);
        $this->fakeSuccessfulIdentity();

        $this->get(route('maddraxikon.oauth.callback', [
            'state' => $attempt['state'],
            'code' => 'code',
        ]))->assertSessionHas('maddraxikon_error');

        $this->assertDatabaseHas('maddraxikon_account_links', [
            'user_id' => $member->id,
            'wiki_user_id' => 99,
            'status' => MaddraxikonAccountLinkStatus::Disconnected->value,
        ]);
    }

    public function test_disconnect_and_reverify_same_identity_preserves_first_verification(): void
    {
        Carbon::setTestNow('2026-07-18 10:00:00');
        $member = $this->createMember();
        $link = MaddraxikonAccountLink::factory()->for($member)->create([
            'oauth_subject' => '42',
            'wiki_user_id' => 42,
            'wiki_username' => 'Alter Name',
            'first_verified_at' => now()->subMonth(),
            'verified_at' => now()->subWeek(),
        ]);
        $firstVerifiedAt = $link->first_verified_at->clone();

        $this->actingAs($member)
            ->delete(route('maddraxikon.oauth.disconnect'))
            ->assertSessionHas('maddraxikon_status');

        $this->assertDatabaseHas('maddraxikon_account_links', [
            'id' => $link->id,
            'status' => MaddraxikonAccountLinkStatus::Disconnected->value,
        ]);

        Carbon::setTestNow('2026-07-19 11:00:00');
        $attempt = $this->beginLink($member);
        $this->fakeSuccessfulIdentity();

        $this->get(route('maddraxikon.oauth.callback', [
            'state' => $attempt['state'],
            'code' => 'code',
        ]))->assertSessionHas('maddraxikon_status');

        $link->refresh();
        $this->assertSame($firstVerifiedAt->getTimestamp(), $link->first_verified_at->getTimestamp());
        $this->assertSame(now()->getTimestamp(), $link->verified_at->getTimestamp());
        $this->assertSame('Wiki Mitglied', $link->wiki_username);
        $this->assertTrue($link->isActive());
        $this->assertDatabaseHas('activities', [
            'user_id' => $member->id,
            'subject_type' => MaddraxikonAccountLink::class,
            'subject_id' => $link->id,
            'action' => Activity::ACTION_MADDRAXIKON_ACCOUNT_LINKED,
        ]);
    }

    public function test_applicant_and_non_member_cannot_start_linking(): void
    {
        $applicant = $this->createMember(Role::Anwaerter);
        $outsider = User::factory()->create(['lat' => 48.0, 'lon' => 11.0]);

        $this->actingAs($applicant)
            ->post(route('maddraxikon.oauth.start'), ['consent' => '1'])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('maddraxikon.oauth.start'), ['consent' => '1'])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_member_losing_eligibility_during_flow_is_rejected(): void
    {
        $member = $this->createMember();
        $attempt = $this->beginLink($member);
        $membersTeam = Team::membersTeam();
        $membersTeam->users()->updateExistingPivot($member->id, [
            'role' => Role::Anwaerter->value,
        ]);

        $this->get(route('maddraxikon.oauth.callback', [
            'state' => $attempt['state'],
            'code' => 'code',
        ]))->assertSessionHas('maddraxikon_error');

        Http::assertNothingSent();
        $this->assertDatabaseCount('maddraxikon_account_links', 0);
    }

    public function test_feature_switch_and_consent_are_enforced(): void
    {
        $member = $this->createMember();

        $this->actingAs($member)
            ->from(route('profile.show'))
            ->post(route('maddraxikon.oauth.start'))
            ->assertRedirect(route('profile.show'))
            ->assertSessionHasErrors('consent');

        config(['maddraxikon.features.linking_enabled' => false]);

        $this->post(route('maddraxikon.oauth.start'), ['consent' => '1'])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('maddraxikon_error');

        Http::assertNothingSent();
    }

    public function test_start_is_rate_limited_and_callback_route_is_exact(): void
    {
        $member = $this->createMember();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($member)
                ->post(route('maddraxikon.oauth.start'), ['consent' => '1'])
                ->assertRedirect();
        }

        $this->post(route('maddraxikon.oauth.start'), ['consent' => '1'])
            ->assertTooManyRequests();

        $this->assertSame(
            'https://maddrax-fanclub.de/oauth/maddraxikon/callback',
            config('services.maddraxikon.redirect_uri'),
        );
        $this->assertSame('/oauth/maddraxikon/callback', route('maddraxikon.oauth.callback', absolute: false));
    }

    public function test_attempt_cache_is_encrypted_expires_and_is_session_bound(): void
    {
        $user = User::factory()->create();
        $store = app(OAuthAttemptStore::class);
        $attempt = $store->create($user, 'session-a');
        $cacheKey = 'maddraxikon:oauth:attempt:'.hash('sha256', $attempt->state);
        $cached = Cache::get($cacheKey);

        $this->assertIsString($cached);
        $this->assertStringNotContainsString($attempt->codeVerifier, $cached);
        $this->assertStringNotContainsString('session-a', $cached);

        $this->expectException(InvalidOAuthAttemptException::class);
        $store->consume($attempt->state, $user, 'session-b');
    }

    public function test_attempt_expires_after_ten_minutes(): void
    {
        Carbon::setTestNow('2026-07-18 10:00:00');
        $user = User::factory()->create();
        $store = app(OAuthAttemptStore::class);
        $attempt = $store->create($user, 'session-a');
        Carbon::setTestNow('2026-07-18 10:11:00');

        $this->expectException(InvalidOAuthAttemptException::class);
        $store->consume($attempt->state, $user, 'session-a');
    }

    /**
     * @param  array<string, mixed>  $apiExtras
     */
    private function fakeSuccessfulIdentity(
        mixed $subject = '42',
        int $apiUserId = 42,
        string $username = 'Wiki Mitglied',
        array $apiExtras = [],
    ): void {
        Http::fake([
            self::TOKEN_URL => Http::response([
                'access_token' => 'access-secret',
                'refresh_token' => 'refresh-secret',
                'token_type' => 'Bearer',
            ]),
            self::PROFILE_URL => Http::response([
                'sub' => $subject,
                'username' => $username,
                'blocked' => false,
                'confirmed_email' => true,
                'email' => 'must-not-be-used@example.com',
            ]),
            self::API_URL.'*' => Http::response([
                'query' => [
                    'users' => [
                        array_merge([
                            'userid' => $apiUserId,
                            'name' => $username,
                        ], $apiExtras),
                    ],
                ],
            ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function beginLink(User $member): array
    {
        $response = $this->actingAs($member)->post(route('maddraxikon.oauth.start'), [
            'consent' => '1',
        ]);

        $response->assertRedirect();

        return $this->redirectQuery($response->headers->get('Location'));
    }

    /**
     * @return array<string, string>
     */
    private function redirectQuery(?string $url): array
    {
        $this->assertIsString($url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    private function assertSecureCallbackHeaders(TestResponse $response): void
    {
        $this->assertSame(
            'no-referrer',
            $response->headers->get('Referrer-Policy'),
        );
        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMember(
        Role $role = Role::Mitglied,
        array $attributes = [],
    ): User {
        $team = Team::membersTeam();
        $user = User::factory()->create(array_merge([
            'current_team_id' => $team->id,
            'lat' => 48.0,
            'lon' => 11.0,
        ], $attributes));
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }
}
