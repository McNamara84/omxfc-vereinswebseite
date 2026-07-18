<?php

namespace App\Services\Maddraxikon;

use App\Services\Maddraxikon\Exceptions\OAuthFlowException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;
use Throwable;

final class OAuthClient
{
    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        $authorizeUrl = $this->trustedMaddraxikonUrl('maddraxikon.oauth.authorize_url');

        return $authorizeUrl.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->requiredConfig('services.maddraxikon.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => $this->identityOnlyScope(),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function identityFromAuthorizationCode(
        #[SensitiveParameter] string $code,
        #[SensitiveParameter] string $codeVerifier,
    ): MaddraxikonIdentity {
        $accessToken = $this->exchangeCode($code, $codeVerifier);
        $profile = $this->fetchProfile($accessToken);

        // The token deliberately goes out of scope here. Refresh tokens are
        // never returned by exchangeCode and neither token is persisted.
        return $this->verifyProfileAgainstActionApi($profile);
    }

    #[\NoDiscard]
    private function exchangeCode(
        #[SensitiveParameter] string $code,
        #[SensitiveParameter] string $codeVerifier,
    ): string {
        try {
            $response = $this->http()
                ->asForm()
                ->post($this->trustedMaddraxikonUrl('maddraxikon.oauth.token_url'), [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'client_id' => $this->requiredConfig('services.maddraxikon.client_id'),
                    'client_secret' => $this->requiredConfig('services.maddraxikon.client_secret'),
                    'redirect_uri' => $this->redirectUri(),
                    'code_verifier' => $codeVerifier,
                ]);
        } catch (Throwable) {
            throw new OAuthFlowException('OAuth token request failed.');
        }

        $payload = $this->successfulJson($response);
        $accessToken = $payload['access_token'] ?? null;
        $tokenType = $payload['token_type'] ?? 'Bearer';

        if (
            ! is_string($accessToken)
            || $accessToken === ''
            || strlen($accessToken) > 8192
            || ! is_string($tokenType)
            || strcasecmp($tokenType, 'Bearer') !== 0
        ) {
            throw new OAuthFlowException('OAuth token response was invalid.');
        }

        return $accessToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchProfile(#[SensitiveParameter] string $accessToken): array
    {
        try {
            $response = $this->http()
                ->withToken($accessToken)
                ->get($this->trustedMaddraxikonUrl('maddraxikon.oauth.profile_url'));
        } catch (Throwable) {
            throw new OAuthFlowException('OAuth profile request failed.');
        }

        return $this->successfulJson($response);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function verifyProfileAgainstActionApi(array $profile): MaddraxikonIdentity
    {
        $subject = $profile['sub'] ?? null;
        $profileUsername = $profile['username'] ?? null;

        if (
            ! is_string($subject)
            || strlen($subject) < 1
            || strlen($subject) > 191
            || preg_match('/[\x00-\x1F\x7F]/', $subject)
            || ! is_string($profileUsername)
            || trim($profileUsername) === ''
            || strlen($profileUsername) > 255
            || preg_match('/[\x00-\x1F\x7F]/u', $profileUsername)
            || $this->profileIsBlocked($profile['blocked'] ?? false)
        ) {
            throw new OAuthFlowException('OAuth profile was invalid.');
        }

        try {
            $response = $this->http()->get($this->trustedMaddraxikonUrl('maddraxikon.api_url'), [
                'action' => 'query',
                'list' => 'users',
                'ususers' => $profileUsername,
                'usprop' => 'blockinfo',
                'format' => 'json',
                'formatversion' => 2,
                'maxlag' => (int) config('maddraxikon.http.maxlag', 5),
            ]);
        } catch (Throwable) {
            throw new OAuthFlowException('MediaWiki identity check failed.');
        }

        $payload = $this->successfulJson($response);
        $users = $payload['query']['users'] ?? null;
        $apiUser = is_array($users) ? ($users[0] ?? null) : null;

        if (
            ! is_array($apiUser)
            || array_key_exists('missing', $apiUser)
            || array_key_exists('invalid', $apiUser)
            || array_key_exists('hidden', $apiUser)
            || ! is_int($apiUser['userid'] ?? null)
            || ! is_string($apiUser['name'] ?? null)
            || $apiUser['userid'] < 1
            || ! hash_equals($profileUsername, $apiUser['name'])
            || $this->apiUserIsBlocked($apiUser)
        ) {
            throw new OAuthFlowException('MediaWiki identity did not match.');
        }

        return new MaddraxikonIdentity(
            oauthSubject: $subject,
            wikiUserId: $apiUser['userid'],
            wikiUsername: $apiUser['name'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function successfulJson(Response $response): array
    {
        if (! $response->successful()) {
            throw new OAuthFlowException('Maddraxikon returned an unsuccessful response.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new OAuthFlowException('Maddraxikon returned invalid JSON.');
        }

        return $payload;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->withUserAgent((string) config(
                'maddraxikon.http.user_agent',
                'OMXFC-Vereinswebsite/1.0 (info@maddrax-fanclub.de)',
            ))
            ->connectTimeout((int) config('maddraxikon.http.connect_timeout', 5))
            ->timeout((int) config('maddraxikon.http.timeout', 15))
            ->withOptions(['allow_redirects' => false]);
    }

    private function redirectUri(): string
    {
        $redirectUri = $this->requiredConfig('services.maddraxikon.redirect_uri');
        $parts = parse_url($redirectUri);
        $appUrl = $this->requiredConfig('app.url');
        $appParts = parse_url($appUrl);

        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || ($parts['path'] ?? '') !== '/oauth/maddraxikon/callback'
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || ! is_array($appParts)
            || isset($appParts['query'])
            || isset($appParts['fragment'])
            || isset($appParts['user'])
            || isset($appParts['pass'])
            || ! $this->sameOrigin($parts, $appParts)
        ) {
            throw new OAuthFlowException('OAuth redirect URI is not configured safely.');
        }

        return $redirectUri;
    }

    private function trustedMaddraxikonUrl(string $configKey): string
    {
        $url = $this->requiredConfig($configKey);
        $baseUrl = $this->requiredConfig('maddraxikon.base_url');
        $urlParts = parse_url($url);
        $baseParts = parse_url($baseUrl);

        if (
            ! is_array($urlParts)
            || ! is_array($baseParts)
            || strtolower((string) ($urlParts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($baseParts['scheme'] ?? '')) !== 'https'
            || ! isset($urlParts['host'], $baseParts['host'])
            || isset($urlParts['query'])
            || isset($urlParts['fragment'])
            || isset($urlParts['user'])
            || isset($urlParts['pass'])
            || isset($baseParts['query'])
            || isset($baseParts['fragment'])
            || isset($baseParts['user'])
            || isset($baseParts['pass'])
            || ! $this->sameOrigin($urlParts, $baseParts)
        ) {
            throw new OAuthFlowException('Maddraxikon endpoint is not configured safely.');
        }

        return rtrim($url, '?');
    }

    private function identityOnlyScope(): string
    {
        $scope = $this->requiredConfig('services.maddraxikon.scope');

        if ($scope !== 'mwoauth-authonly') {
            throw new OAuthFlowException('OAuth scope is not identity-only.');
        }

        return $scope;
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function sameOrigin(array $left, array $right): bool
    {
        $leftScheme = strtolower((string) ($left['scheme'] ?? ''));
        $rightScheme = strtolower((string) ($right['scheme'] ?? ''));
        $leftHost = (string) ($left['host'] ?? '');
        $rightHost = (string) ($right['host'] ?? '');

        if ($leftScheme === '' || $leftHost === '' || $rightScheme === '' || $rightHost === '') {
            return false;
        }

        $defaultPorts = ['http' => 80, 'https' => 443];
        $leftPort = $left['port'] ?? ($defaultPorts[$leftScheme] ?? null);
        $rightPort = $right['port'] ?? ($defaultPorts[$rightScheme] ?? null);

        return $leftScheme === $rightScheme
            && strcasecmp($leftHost, $rightHost) === 0
            && $leftPort === $rightPort;
    }

    private function requiredConfig(string $key): string
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            throw new OAuthFlowException("Required configuration [{$key}] is missing.");
        }

        return $value;
    }

    private function profileIsBlocked(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    /**
     * @param  array<string, mixed>  $apiUser
     */
    private function apiUserIsBlocked(array $apiUser): bool
    {
        foreach (['blockid', 'blockedby', 'blockedbyid', 'blockedtimestamp', 'blockexpiry'] as $key) {
            if (array_key_exists($key, $apiUser)) {
                return true;
            }
        }

        return false;
    }
}
