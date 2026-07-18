<?php

namespace Tests\Feature;

use App\Services\Maddraxikon\Exceptions\OAuthFlowException;
use App\Services\Maddraxikon\OAuthClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaddraxikonOAuthClientSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://de.maddraxikon.com/rest.php/oauth2/access_token';

    private const PROFILE_URL = 'https://de.maddraxikon.com/rest.php/oauth2/resource/profile';

    private const API_URL = 'https://de.maddraxikon.com/api.php';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://maddrax-fanclub.de',
            'maddraxikon.base_url' => 'https://de.maddraxikon.com',
            'maddraxikon.api_url' => self::API_URL,
            'maddraxikon.oauth.authorize_url' => 'https://de.maddraxikon.com/rest.php/oauth2/authorize',
            'maddraxikon.oauth.token_url' => self::TOKEN_URL,
            'maddraxikon.oauth.profile_url' => self::PROFILE_URL,
            'services.maddraxikon.client_id' => 'client-id',
            'services.maddraxikon.client_secret' => 'client-secret',
            'services.maddraxikon.redirect_uri' => 'https://maddrax-fanclub.de/oauth/maddraxikon/callback',
            'services.maddraxikon.scope' => 'mwoauth-authonly',
        ]);
    }

    public function test_client_rejects_non_https_or_wrong_callback_path(): void
    {
        $client = app(OAuthClient::class);

        config(['services.maddraxikon.redirect_uri' => 'http://maddrax-fanclub.de/oauth/maddraxikon/callback']);

        try {
            $client->authorizationUrl(str_repeat('a', 43), str_repeat('b', 43));
            $this->fail('An insecure callback URL was accepted.');
        } catch (OAuthFlowException) {
            $this->assertTrue(true);
        }

        config(['services.maddraxikon.redirect_uri' => 'https://maddrax-fanclub.de/attacker-controlled']);

        $this->expectException(OAuthFlowException::class);
        $client->authorizationUrl(str_repeat('a', 43), str_repeat('b', 43));
    }

    public function test_callback_origin_must_exactly_match_app_url_origin(): void
    {
        $client = app(OAuthClient::class);
        config([
            'services.maddraxikon.redirect_uri' => 'https://evil.example/oauth/maddraxikon/callback',
        ]);

        try {
            $client->authorizationUrl(str_repeat('a', 43), str_repeat('b', 43));
            $this->fail('A callback on another HTTPS origin was accepted.');
        } catch (OAuthFlowException) {
            $this->addToAssertionCount(1);
        }

        config([
            'services.maddraxikon.redirect_uri' => 'https://maddrax-fanclub.de:8443/oauth/maddraxikon/callback',
        ]);

        $this->expectException(OAuthFlowException::class);
        $client->authorizationUrl(str_repeat('a', 43), str_repeat('b', 43));
    }

    public function test_client_rejects_maddraxikon_endpoint_on_another_host(): void
    {
        config([
            'maddraxikon.oauth.authorize_url' => 'https://evil.example/oauth2/authorize',
        ]);

        $this->expectException(OAuthFlowException::class);

        app(OAuthClient::class)->authorizationUrl(str_repeat('a', 43), str_repeat('b', 43));
    }

    public function test_client_rejects_maddraxikon_endpoint_on_another_port(): void
    {
        config([
            'maddraxikon.oauth.authorize_url' => 'https://de.maddraxikon.com:8443/rest.php/oauth2/authorize',
        ]);

        $this->expectException(OAuthFlowException::class);

        app(OAuthClient::class)->authorizationUrl(str_repeat('a', 43), str_repeat('b', 43));
    }

    public function test_profile_redirect_is_not_followed_with_bearer_token(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response([
                'access_token' => 'access-secret',
                'token_type' => 'Bearer',
            ]),
            self::PROFILE_URL => Http::response('', 302, [
                'Location' => 'https://evil.example/capture',
            ]),
            'https://evil.example/*' => Http::response(['captured' => true]),
        ]);

        try {
            app(OAuthClient::class)->identityFromAuthorizationCode('code', str_repeat('v', 64));
            $this->fail('A redirecting profile endpoint was accepted.');
        } catch (OAuthFlowException) {
            $this->assertTrue(true);
        }

        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request): bool => str_starts_with($request->url(), 'https://evil.example/'));
    }

    public function test_client_rejects_profile_username_that_does_not_match_action_api(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response([
                'access_token' => 'access-secret',
                'token_type' => 'Bearer',
            ]),
            self::PROFILE_URL => Http::response([
                'sub' => '42',
                'username' => 'OAuth Name',
            ]),
            self::API_URL.'*' => Http::response([
                'query' => [
                    'users' => [[
                        'userid' => 42,
                        'name' => 'Anderer Name',
                    ]],
                ],
            ]),
        ]);

        $this->expectException(OAuthFlowException::class);

        app(OAuthClient::class)->identityFromAuthorizationCode('code', str_repeat('v', 64));
    }

    public function test_client_rejects_blocked_profile_before_action_api_call(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response([
                'access_token' => 'access-secret',
                'token_type' => 'Bearer',
            ]),
            self::PROFILE_URL => Http::response([
                'sub' => '42',
                'username' => 'Wiki Mitglied',
                'blocked' => true,
            ]),
            self::API_URL.'*' => Http::response([
                'query' => ['users' => []],
            ]),
        ]);

        try {
            app(OAuthClient::class)->identityFromAuthorizationCode('code', str_repeat('v', 64));
            $this->fail('A blocked profile was accepted.');
        } catch (OAuthFlowException) {
            $this->assertTrue(true);
        }

        Http::assertSentCount(2);
    }

    public function test_client_rejects_invalid_json_and_never_uses_refresh_token(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response([
                'access_token' => 'access-secret',
                'refresh_token' => 'refresh-secret',
                'token_type' => 'Bearer',
            ]),
            self::PROFILE_URL => Http::response('not-json', 200, [
                'Content-Type' => 'application/json',
            ]),
        ]);

        $this->expectException(OAuthFlowException::class);

        app(OAuthClient::class)->identityFromAuthorizationCode('code', str_repeat('v', 64));
    }
}
