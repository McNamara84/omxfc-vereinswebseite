<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Enums\Role;
use App\Mail\MaddraxikonAccountLinked;
use App\Mail\ProfileContactUpdated;
use App\Models\Activity;
use App\Models\MaddraxikonAccountLink;
use App\Models\Team;
use App\Models\User;
use App\Services\Maddraxikon\Exceptions\InvalidOAuthAttemptException;
use App\Services\Maddraxikon\OAuthAttemptStore;
use Carbon\Carbon;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonOAuthTestFixtures::class);

beforeEach(function (): void {
    config(['app.url' => 'https://maddrax-fanclub.de', 'maddraxikon.features.linking_enabled' => true, 'maddraxikon.base_url' => 'https://de.maddraxikon.com', 'maddraxikon.api_url' => self::API_URL, 'maddraxikon.oauth.authorize_url' => self::AUTHORIZE_URL, 'maddraxikon.oauth.token_url' => self::TOKEN_URL, 'maddraxikon.oauth.profile_url' => self::PROFILE_URL, 'maddraxikon.wiki_key' => 'maddraxikon-de', 'maddraxikon.consent_version' => '2026-07-18', 'services.maddraxikon.client_id' => 'client-id', 'services.maddraxikon.client_secret' => 'client-secret', 'services.maddraxikon.redirect_uri' => 'https://maddrax-fanclub.de/oauth/maddraxikon/callback', 'services.maddraxikon.scope' => 'mwoauth-authonly']);
    Mail::fake();
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('start uses fixed callback state and pkce s256', function (): void {
    $member = $this->createMember();
    $response = $this->actingAs($member)->post(route('maddraxikon.oauth.start'), ['consent' => '1']);
    $response->assertRedirect();
    $query = $this->redirectQuery($response->headers->get('Location'));
    expect($response->headers->get('Location'))->toStartWith(self::AUTHORIZE_URL.'?')
        ->and($query['response_type'])->toBe('code')
        ->and($query['client_id'])->toBe('client-id')
        ->and($query['redirect_uri'])->toBe('https://maddrax-fanclub.de/oauth/maddraxikon/callback')
        ->and($query['scope'])->toBe('mwoauth-authonly')
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['state'])->toMatch('/\A[A-Za-z0-9_-]{43}\z/')
        ->and($query['code_challenge'])->toMatch('/\A[A-Za-z0-9_-]{43}\z/')
        ->and($query)->not->toHaveKey('email');
});

test('expired login session still gets secure callback headers', function (): void {
    $response = $this->get(route('maddraxikon.oauth.callback', ['state' => str_repeat('a', 43), 'code' => 'must-not-leak']));
    $response->assertRedirect(route('login'));
    $this->assertSecureCallbackHeaders($response);
    $this->assertDatabaseCount('maddraxikon_account_links', 0);
    Http::assertNothingSent();
});

test('rate limited callback still gets secure headers', function (): void {
    $response = null;
    foreach (range(1, 11) as $attempt) {
        $response = $this->get(route('maddraxikon.oauth.callback', ['state' => str_repeat('b', 43), 'code' => 'must-not-leak-'.$attempt]));
    }
    expect($response)->toBeInstanceOf(TestResponse::class);
    $response->assertTooManyRequests();
    $this->assertSecureCallbackHeaders($response);
    Http::assertNothingSent();
});

test('valid callback links account and queues information mail', function (): void {
    $member = $this->createMember(attributes: ['email' => 'member@example.com']);
    $stateAndChallenge = $this->beginLink($member);
    $this->fakeSuccessfulIdentity();
    $response = $this->get(route('maddraxikon.oauth.callback', ['state' => $stateAndChallenge['state'], 'code' => 'single-use-code']));
    $response->assertRedirect(route('profile.show'))->assertSessionHas('maddraxikon_status');
    $this->assertSecureCallbackHeaders($response);
    $link = MaddraxikonAccountLink::query()->where('user_id', $member->id)->firstOrFail();
    expect($link->oauth_subject)->toBe('42')
        ->and($link->wiki_user_id)->toBe(42)
        ->and($link->wiki_username)->toBe('Wiki Mitglied')
        ->and($link->status)->toBe(MaddraxikonAccountLinkStatus::Active)
        ->and($link->verification_method)->toBe('oauth2')
        ->and($link->consent_version)->toBe('2026-07-18')
        ->and($link->disconnected_at)->toBeNull();
    $this->assertDatabaseHas('activities', ['user_id' => $member->id, 'subject_type' => MaddraxikonAccountLink::class, 'subject_id' => $link->id, 'action' => Activity::ACTION_MADDRAXIKON_ACCOUNT_LINKED]);
    Mail::assertQueued(MaddraxikonAccountLinked::class, fn (MaddraxikonAccountLinked $mail): bool => $mail->hasTo('member@example.com') && $mail->wikiUsername === 'Wiki Mitglied');
    $expectedChallenge = $stateAndChallenge['code_challenge'];
    Http::assertSent(function (ClientRequest $request) use ($expectedChallenge): bool {
        if ($request->url() !== self::TOKEN_URL) {
            return false;
        }
        $verifier = $request['code_verifier'];
        $calculated = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return $request->method() === 'POST' && $request['code'] === 'single-use-code' && $request['client_secret'] === 'client-secret' && $request['redirect_uri'] === 'https://maddrax-fanclub.de/oauth/maddraxikon/callback' && hash_equals($expectedChallenge, $calculated);
    });
    Http::assertSent(fn (ClientRequest $request): bool => str_starts_with($request->url(), self::API_URL.'?') && $request['action'] === 'query' && $request['list'] === 'users' && $request['ususers'] === 'Wiki Mitglied' && ! $request->hasHeader('Authorization'));
    $databaseValues = DB::table('maddraxikon_account_links')->get()->toJson();
    expect($databaseValues)->not->toContain('access-secret')->not->toContain('refresh-secret');
});

test('callback state is single use', function (): void {
    $member = $this->createMember();
    $attempt = $this->beginLink($member);
    $this->fakeSuccessfulIdentity();
    $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => 'first-code']))->assertSessionHas('maddraxikon_status');
    $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => 'second-code']))->assertSessionHas('maddraxikon_error');
    Http::assertSentCount(3);
    Mail::assertQueued(MaddraxikonAccountLinked::class, 1);
    $this->assertDatabaseCount('maddraxikon_account_links', 1);
});

test('reauth of active link updates consent without duplicate link activity', function (): void {
    $member = $this->createMember();
    $this->fakeSuccessfulIdentity();
    foreach (['first-code', 'reauth-code'] as $code) {
        if ($code === 'reauth-code') {
            config(['maddraxikon.consent_version' => '2026-08-10-ratings']);
        }
        $attempt = $this->beginLink($member);
        $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => $code]))->assertSessionHas('maddraxikon_status');
    }
    $link = MaddraxikonAccountLink::query()->where('user_id', $member->id)->sole();
    expect($link->consent_version)->toBe('2026-08-10-ratings')
        ->and(Activity::query()->where('user_id', $member->id)->where('subject_type', MaddraxikonAccountLink::class)->where('subject_id', $link->id)->where('action', Activity::ACTION_MADDRAXIKON_ACCOUNT_LINKED)->count())->toBe(1);
});

test('callback is bound to original laravel user', function (): void {
    $member = $this->createMember();
    $otherMember = $this->createMember();
    $attempt = $this->beginLink($member);
    $this->actingAs($otherMember);
    $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => 'code']))->assertSessionHas('maddraxikon_error');
    $this->actingAs($member)->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => 'code']))->assertSessionHas('maddraxikon_error');
    Http::assertNothingSent();
    $this->assertDatabaseCount('maddraxikon_account_links', 0);
});

test('provider denial consumes attempt without token request', function (): void {
    $member = $this->createMember();
    $attempt = $this->beginLink($member);
    $response = $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'error' => 'access_denied', 'error_description' => 'untrusted provider text']));
    $response->assertRedirect(route('profile.show'))->assertSessionHas('maddraxikon_error', 'Die Verknüpfung wurde im Maddraxikon nicht bestätigt.');
    $this->assertSecureCallbackHeaders($response);
    Http::assertNothingSent();
    $this->assertDatabaseCount('maddraxikon_account_links', 0);
});

test('callback accepts opaque subject and resolves local id from confirmed username', function (): void {
    $member = $this->createMember();
    $attempt = $this->beginLink($member);
    $this->fakeSuccessfulIdentity(subject: 'opaque:wiki-identity:42', apiUserId: 43);
    $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => 'code']))->assertSessionHas('maddraxikon_status');
    $this->assertDatabaseHas('maddraxikon_account_links', ['user_id' => $member->id, 'oauth_subject' => 'opaque:wiki-identity:42', 'wiki_user_id' => 43, 'wiki_username' => 'Wiki Mitglied']);
});

test('callback rejects blocked wiki account', function (): void {
    $member = $this->createMember();
    $attempt = $this->beginLink($member);
    $this->fakeSuccessfulIdentity(apiExtras: ['blockedby' => 'Admin', 'blockid' => 5]);
    $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => 'code']))->assertSessionHas('maddraxikon_error');
    $this->assertDatabaseCount('maddraxikon_account_links', 0);
});

test('callback rejects malformed token response', function (): void {
    $member = $this->createMember();
    $attempt = $this->beginLink($member);
    Http::fake([self::TOKEN_URL => Http::response(['refresh_token' => 'refresh-secret'], 200)]);
    $response = $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => 'code']));
    $response->assertSessionHas('maddraxikon_error');
    $this->assertSecureCallbackHeaders($response);
    $this->assertDatabaseCount('maddraxikon_account_links', 0);
});

test('callback rejects non string empty control or oversized subjects', function (): void {
    $member = $this->createMember();
    $invalidSubjects = [42, '', "control\x00subject", str_repeat('x', 192)];
    foreach ($invalidSubjects as $index => $invalidSubject) {
        $attempt = $this->beginLink($member);
        $this->fakeSuccessfulIdentity(subject: $invalidSubject);
        $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => "code-{$index}"]))->assertSessionHas('maddraxikon_error');
    }
    $this->assertDatabaseCount('maddraxikon_account_links', 0);
    Mail::assertNothingQueued();
});

test('historical wiki identity cannot be claimed by another member', function (): void {
    $owner = $this->createMember();
    $claimant = $this->createMember();
    MaddraxikonAccountLink::factory()->disconnected()->for($owner)->create(['oauth_subject' => '42', 'wiki_user_id' => 42, 'wiki_username' => 'Wiki Mitglied']);
    $attempt = $this->beginLink($claimant);
    $this->fakeSuccessfulIdentity();
    $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => 'code']))->assertSessionHas('maddraxikon_error');
    $this->assertDatabaseMissing('maddraxikon_account_links', ['user_id' => $claimant->id]);
    Mail::assertNothingQueued();
});

test('member cannot switch to a different wiki identity in self service', function (): void {
    $member = $this->createMember();
    MaddraxikonAccountLink::factory()->disconnected()->for($member)->create(['oauth_subject' => '99', 'wiki_user_id' => 99, 'wiki_username' => 'Altes Wiki-Konto']);
    $attempt = $this->beginLink($member);
    $this->fakeSuccessfulIdentity();
    $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => 'code']))->assertSessionHas('maddraxikon_error');
    $this->assertDatabaseHas('maddraxikon_account_links', ['user_id' => $member->id, 'wiki_user_id' => 99, 'status' => MaddraxikonAccountLinkStatus::Disconnected->value]);
});

test('disconnect and reverify same identity preserves first verification', function (): void {
    Carbon::setTestNow('2026-07-18 10:00:00');
    $member = $this->createMember(attributes: ['contact_release_maddraxikon' => true, 'contact_released_at' => now()->subYear()]);
    $link = MaddraxikonAccountLink::factory()->for($member)->create(['oauth_subject' => '42', 'wiki_user_id' => 42, 'wiki_username' => 'Alter Name', 'first_verified_at' => now()->subMonth(), 'verified_at' => now()->subWeek()]);
    $firstVerifiedAt = $link->first_verified_at->clone();
    $this->actingAs($member)->delete(route('maddraxikon.oauth.disconnect'))->assertSessionHas('maddraxikon_status', 'Die Maddraxikon-Verknüpfung ist getrennt und das Maddraxikon-Profil nicht freigegeben. Bereits gutgeschriebene Baxx bleiben erhalten.');
    $this->assertDatabaseHas('maddraxikon_account_links', ['id' => $link->id, 'status' => MaddraxikonAccountLinkStatus::Disconnected->value]);
    $member->refresh();
    expect($member->contact_release_maddraxikon)->toBeFalse()
        ->and($member->contact_released_at?->getTimestamp())->toBe(now()->getTimestamp());
    Mail::assertQueued(ProfileContactUpdated::class, fn (ProfileContactUpdated $mail): bool => $mail->changedContactLabels === ['Maddraxikon'] && $mail->hasTo('info@maddraxikon.com'));
    Carbon::setTestNow('2026-07-19 11:00:00');
    $attempt = $this->beginLink($member);
    $this->fakeSuccessfulIdentity();
    $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => 'code']))->assertSessionHas('maddraxikon_status');
    $link->refresh();
    expect($link->first_verified_at->getTimestamp())->toBe($firstVerifiedAt->getTimestamp())
        ->and($link->verified_at->getTimestamp())->toBe(now()->getTimestamp())
        ->and($link->wiki_username)->toBe('Wiki Mitglied')
        ->and($link->isActive())->toBeTrue()
        ->and($member->refresh()->contact_release_maddraxikon)->toBeFalse();
    $this->assertDatabaseHas('activities', ['user_id' => $member->id, 'subject_type' => MaddraxikonAccountLink::class, 'subject_id' => $link->id, 'action' => Activity::ACTION_MADDRAXIKON_ACCOUNT_LINKED]);
});

test('disconnect without profile release keeps contact timestamp and is idempotent', function (): void {
    Carbon::setTestNow('2026-07-18 10:00:00');
    $originalContactTime = now()->subYear();
    $member = $this->createMember(attributes: ['contact_release_maddraxikon' => false, 'contact_released_at' => $originalContactTime]);
    $link = MaddraxikonAccountLink::factory()->for($member)->create();
    $this->actingAs($member)->delete(route('maddraxikon.oauth.disconnect'))->assertSessionHas('maddraxikon_status', 'Die Maddraxikon-Verknüpfung ist getrennt und das Maddraxikon-Profil nicht freigegeben. Bereits gutgeschriebene Baxx bleiben erhalten.');
    $member->refresh();
    expect($member->contact_release_maddraxikon)->toBeFalse()
        ->and($member->contact_released_at?->getTimestamp())->toBe($originalContactTime->getTimestamp());
    Mail::assertNotQueued(ProfileContactUpdated::class);
    Carbon::setTestNow('2026-07-19 11:00:00');
    $this->actingAs($member)->delete(route('maddraxikon.oauth.disconnect'))->assertSessionHas('maddraxikon_status', 'Die Maddraxikon-Verknüpfung ist getrennt und das Maddraxikon-Profil nicht freigegeben. Bereits gutgeschriebene Baxx bleiben erhalten.');
    expect($member->refresh()->contact_released_at?->getTimestamp())->toBe($originalContactTime->getTimestamp())
        ->and(Carbon::parse('2026-07-18 10:00:00')->getTimestamp())->toBe($link->fresh()->disconnected_at?->getTimestamp());
    Mail::assertNotQueued(ProfileContactUpdated::class);
});

test('disconnect repairs release flag on an already disconnected link', function (): void {
    Carbon::setTestNow('2026-07-18 10:00:00');
    $member = $this->createMember(attributes: ['contact_release_maddraxikon' => true]);
    MaddraxikonAccountLink::factory()->disconnected()->for($member)->create();
    $this->actingAs($member)->delete(route('maddraxikon.oauth.disconnect'))->assertSessionHas('maddraxikon_status');
    expect($member->refresh()->contact_release_maddraxikon)->toBeFalse()
        ->and($member->contact_released_at?->getTimestamp())->toBe(now()->getTimestamp());
    Mail::assertQueued(ProfileContactUpdated::class);
});

test('applicant and non member cannot start linking', function (): void {
    $applicant = $this->createMember(Role::Anwaerter);
    $outsider = User::factory()->create(['lat' => 48.0, 'lon' => 11.0]);
    $this->actingAs($applicant)->post(route('maddraxikon.oauth.start'), ['consent' => '1'])->assertForbidden();
    $this->actingAs($outsider)->post(route('maddraxikon.oauth.start'), ['consent' => '1'])->assertForbidden();
    Http::assertNothingSent();
});

test('member losing eligibility during flow is rejected', function (): void {
    $member = $this->createMember();
    $attempt = $this->beginLink($member);
    $membersTeam = Team::membersTeam();
    $membersTeam->users()->updateExistingPivot($member->id, ['role' => Role::Anwaerter->value]);
    $this->get(route('maddraxikon.oauth.callback', ['state' => $attempt['state'], 'code' => 'code']))->assertSessionHas('maddraxikon_error');
    Http::assertNothingSent();
    $this->assertDatabaseCount('maddraxikon_account_links', 0);
});

test('feature switch and consent are enforced', function (): void {
    $member = $this->createMember();
    $this->actingAs($member)->from(route('profile.show'))->post(route('maddraxikon.oauth.start'))->assertRedirect(route('profile.show'))->assertSessionHasErrors('consent');
    config(['maddraxikon.features.linking_enabled' => false]);
    $this->post(route('maddraxikon.oauth.start'), ['consent' => '1'])->assertRedirect(route('profile.show'))->assertSessionHas('maddraxikon_error');
    Http::assertNothingSent();
});

test('start is rate limited and callback route is exact', function (): void {
    $member = $this->createMember();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->actingAs($member)->post(route('maddraxikon.oauth.start'), ['consent' => '1'])->assertRedirect();
    }
    $this->post(route('maddraxikon.oauth.start'), ['consent' => '1'])->assertTooManyRequests();
    expect(config('services.maddraxikon.redirect_uri'))->toBe('https://maddrax-fanclub.de/oauth/maddraxikon/callback')
        ->and(route('maddraxikon.oauth.callback', absolute: false))->toBe('/oauth/maddraxikon/callback');
});

test('attempt cache is encrypted expires and is session bound', function (): void {
    $user = User::factory()->create();
    $store = app(OAuthAttemptStore::class);
    $attempt = $store->create($user, 'session-a');
    $cacheKey = 'maddraxikon:oauth:attempt:'.hash('sha256', $attempt->state);
    $cached = Cache::get($cacheKey);
    expect($cached)->toBeString()->not->toContain($attempt->codeVerifier)->not->toContain('session-a')
        ->and(fn () => $store->consume($attempt->state, $user, 'session-b'))->toThrow(InvalidOAuthAttemptException::class);
});

test('attempt expires after ten minutes', function (): void {
    Carbon::setTestNow('2026-07-18 10:00:00');
    $user = User::factory()->create();
    $store = app(OAuthAttemptStore::class);
    $attempt = $store->create($user, 'session-a');
    Carbon::setTestNow('2026-07-18 10:11:00');
    expect(fn () => $store->consume($attempt->state, $user, 'session-a'))->toThrow(InvalidOAuthAttemptException::class);
});
