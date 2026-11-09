<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Maddraxikon\Exceptions\OAuthFlowException;
use App\Services\Maddraxikon\OAuthClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonOAuthClientSecurityTestFixtures::class);

beforeEach(function (): void {
    config(['app.url' => 'https://maddrax-fanclub.de', 'maddraxikon.base_url' => 'https://de.maddraxikon.com', 'maddraxikon.api_url' => self::API_URL, 'maddraxikon.oauth.authorize_url' => 'https://de.maddraxikon.com/rest.php/oauth2/authorize', 'maddraxikon.oauth.token_url' => self::TOKEN_URL, 'maddraxikon.oauth.profile_url' => self::PROFILE_URL, 'services.maddraxikon.client_id' => 'client-id', 'services.maddraxikon.client_secret' => 'client-secret', 'services.maddraxikon.redirect_uri' => 'https://maddrax-fanclub.de/oauth/maddraxikon/callback', 'services.maddraxikon.scope' => 'mwoauth-authonly']);
});

test('client rejects non https or wrong callback path', function (): void {
    $client = app(OAuthClient::class);
    config(['services.maddraxikon.redirect_uri' => 'http://maddrax-fanclub.de/oauth/maddraxikon/callback']);
    try {
        $client->authorizationUrl(str_repeat('a', 43), str_repeat('b', 43));
        $this->fail('An insecure callback URL was accepted.');
    } catch (OAuthFlowException) {
        expect(true)->toBeTrue();
    }
    config(['services.maddraxikon.redirect_uri' => 'https://maddrax-fanclub.de/attacker-controlled']);
    expect(fn () => $client->authorizationUrl(str_repeat('a', 43), str_repeat('b', 43)))->toThrow(OAuthFlowException::class);
});

test('callback origin must exactly match app url origin', function (): void {
    $client = app(OAuthClient::class);
    config(['services.maddraxikon.redirect_uri' => 'https://evil.example/oauth/maddraxikon/callback']);
    try {
        $client->authorizationUrl(str_repeat('a', 43), str_repeat('b', 43));
        $this->fail('A callback on another HTTPS origin was accepted.');
    } catch (OAuthFlowException) {
        $this->addToAssertionCount(1);
    }
    config(['services.maddraxikon.redirect_uri' => 'https://maddrax-fanclub.de:8443/oauth/maddraxikon/callback']);
    expect(fn () => $client->authorizationUrl(str_repeat('a', 43), str_repeat('b', 43)))->toThrow(OAuthFlowException::class);
});

test('client rejects maddraxikon endpoint on another host', function (): void {
    config(['maddraxikon.oauth.authorize_url' => 'https://evil.example/oauth2/authorize']);
    expect(fn () => app(OAuthClient::class)->authorizationUrl(str_repeat('a', 43), str_repeat('b', 43)))->toThrow(OAuthFlowException::class);
});

test('client rejects maddraxikon endpoint on another port', function (): void {
    config(['maddraxikon.oauth.authorize_url' => 'https://de.maddraxikon.com:8443/rest.php/oauth2/authorize']);
    expect(fn () => app(OAuthClient::class)->authorizationUrl(str_repeat('a', 43), str_repeat('b', 43)))->toThrow(OAuthFlowException::class);
});

test('profile redirect is not followed with bearer token', function (): void {
    Http::fake([self::TOKEN_URL => Http::response(['access_token' => 'access-secret', 'token_type' => 'Bearer']), self::PROFILE_URL => Http::response('', 302, ['Location' => 'https://evil.example/capture']), 'https://evil.example/*' => Http::response(['captured' => true])]);
    try {
        app(OAuthClient::class)->identityFromAuthorizationCode('code', str_repeat('v', 64));
        $this->fail('A redirecting profile endpoint was accepted.');
    } catch (OAuthFlowException) {
        expect(true)->toBeTrue();
    }
    Http::assertSentCount(2);
    Http::assertNotSent(fn ($request): bool => str_starts_with($request->url(), 'https://evil.example/'));
});

test('client rejects profile username that does not match action api', function (): void {
    Http::fake([self::TOKEN_URL => Http::response(['access_token' => 'access-secret', 'token_type' => 'Bearer']), self::PROFILE_URL => Http::response(['sub' => '42', 'username' => 'OAuth Name']), self::API_URL.'*' => Http::response(['query' => ['users' => [['userid' => 42, 'name' => 'Anderer Name']]]])]);
    expect(fn () => app(OAuthClient::class)->identityFromAuthorizationCode('code', str_repeat('v', 64)))->toThrow(OAuthFlowException::class);
});

test('client rejects blocked profile before action api call', function (): void {
    Http::fake([self::TOKEN_URL => Http::response(['access_token' => 'access-secret', 'token_type' => 'Bearer']), self::PROFILE_URL => Http::response(['sub' => '42', 'username' => 'Wiki Mitglied', 'blocked' => true]), self::API_URL.'*' => Http::response(['query' => ['users' => []]])]);
    try {
        app(OAuthClient::class)->identityFromAuthorizationCode('code', str_repeat('v', 64));
        $this->fail('A blocked profile was accepted.');
    } catch (OAuthFlowException) {
        expect(true)->toBeTrue();
    }
    Http::assertSentCount(2);
});

test('client rejects invalid json and never uses refresh token', function (): void {
    Http::fake([self::TOKEN_URL => Http::response(['access_token' => 'access-secret', 'refresh_token' => 'refresh-secret', 'token_type' => 'Bearer']), self::PROFILE_URL => Http::response('not-json', 200, ['Content-Type' => 'application/json'])]);
    expect(fn () => app(OAuthClient::class)->identityFromAuthorizationCode('code', str_repeat('v', 64)))->toThrow(OAuthFlowException::class);
});
