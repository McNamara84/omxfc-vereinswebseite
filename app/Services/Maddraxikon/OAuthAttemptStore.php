<?php

namespace App\Services\Maddraxikon;

use App\Models\User;
use App\Services\Maddraxikon\Exceptions\InvalidOAuthAttemptException;
use App\Services\Maddraxikon\Exceptions\OAuthFlowException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

final class OAuthAttemptStore
{
    private const CACHE_PREFIX = 'maddraxikon:oauth:attempt:';

    private const LIFETIME_MINUTES = 10;

    public function create(
        User $user,
        string $sessionId,
        ?string $consentVersion = null,
    ): OAuthAuthorizationAttempt {
        $createdAt = now()->toImmutable();
        $consentVersion ??= (string) config('maddraxikon.consent_version');

        if (! $this->validConsentVersion($consentVersion)) {
            throw new OAuthFlowException('OAuth consent version is not configured safely.');
        }

        $state = $this->base64UrlEncode(random_bytes(32));
        $codeVerifier = $this->base64UrlEncode(random_bytes(64));
        $codeChallenge = $this->base64UrlEncode(hash('sha256', $codeVerifier, true));
        $expiresAt = $createdAt->addMinutes(self::LIFETIME_MINUTES);

        $payload = json_encode([
            'state_hash' => hash('sha256', $state),
            'user_id' => $user->getKey(),
            'session_hash' => $this->sessionHash($sessionId),
            'code_verifier' => $codeVerifier,
            'return_route' => 'profile.show',
            'consent_version' => $consentVersion,
            'consented_at' => $createdAt->getTimestamp(),
            'created_at' => $createdAt->getTimestamp(),
            'expires_at' => $expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        Cache::put(
            $this->cacheKey($state),
            Crypt::encryptString($payload),
            $expiresAt,
        );

        return new OAuthAuthorizationAttempt(
            state: $state,
            codeVerifier: $codeVerifier,
            codeChallenge: $codeChallenge,
            consentVersion: $consentVersion,
            consentedAt: $createdAt,
        );
    }

    /**
     * Atomically consumes an attempt. Invalid session or user bindings consume
     * the value as well, preventing a captured callback from being retried.
     */
    public function consume(string $state, User $user, string $sessionId): OAuthAuthorizationAttempt
    {
        if (! $this->validStateShape($state)) {
            throw new InvalidOAuthAttemptException;
        }

        $cacheKey = $this->cacheKey($state);

        try {
            return Cache::lock($cacheKey.':consume', 5)->block(2, function () use ($cacheKey, $sessionId, $state, $user) {
                $encryptedPayload = Cache::pull($cacheKey);

                if (! is_string($encryptedPayload) || $encryptedPayload === '') {
                    throw new InvalidOAuthAttemptException;
                }

                try {
                    $payload = json_decode(
                        Crypt::decryptString($encryptedPayload),
                        true,
                        flags: JSON_THROW_ON_ERROR,
                    );
                } catch (Throwable) {
                    throw new InvalidOAuthAttemptException;
                }

                if (! is_array($payload) || ! $this->validPayload($payload, $state, $user, $sessionId)) {
                    throw new InvalidOAuthAttemptException;
                }

                $codeVerifier = $payload['code_verifier'];

                return new OAuthAuthorizationAttempt(
                    state: $state,
                    codeVerifier: $codeVerifier,
                    codeChallenge: $this->base64UrlEncode(hash('sha256', $codeVerifier, true)),
                    consentVersion: $payload['consent_version'],
                    consentedAt: CarbonImmutable::createFromTimestampUTC($payload['consented_at'])
                        ->setTimezone((string) config('app.timezone', 'UTC')),
                );
            });
        } catch (LockTimeoutException) {
            throw new InvalidOAuthAttemptException;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validPayload(array $payload, string $state, User $user, string $sessionId): bool
    {
        if (
            ! is_string($payload['state_hash'] ?? null)
            || ! is_int($payload['user_id'] ?? null)
            || ! is_string($payload['session_hash'] ?? null)
            || ! is_string($payload['code_verifier'] ?? null)
            || ! is_string($payload['return_route'] ?? null)
            || ! is_string($payload['consent_version'] ?? null)
            || ! is_int($payload['consented_at'] ?? null)
            || ! is_int($payload['created_at'] ?? null)
            || ! is_int($payload['expires_at'] ?? null)
            || ! $this->validConsentVersion($payload['consent_version'])
        ) {
            return false;
        }

        return hash_equals(hash('sha256', $state), $payload['state_hash'])
            && $payload['user_id'] === (int) $user->getKey()
            && hash_equals($this->sessionHash($sessionId), $payload['session_hash'])
            && $payload['return_route'] === 'profile.show'
            && $payload['consented_at'] === $payload['created_at']
            && $payload['created_at'] <= now()->getTimestamp()
            && $payload['expires_at'] >= now()->getTimestamp()
            && $this->validCodeVerifier($payload['code_verifier']);
    }

    private function validConsentVersion(string $consentVersion): bool
    {
        return trim($consentVersion) === $consentVersion
            && strlen($consentVersion) >= 1
            && strlen($consentVersion) <= 64
            && ! preg_match('/[\x00-\x1F\x7F]/', $consentVersion);
    }

    private function validStateShape(string $state): bool
    {
        return (bool) preg_match('/\A[A-Za-z0-9_-]{43}\z/', $state);
    }

    private function validCodeVerifier(string $codeVerifier): bool
    {
        return Str::length($codeVerifier) >= 43
            && Str::length($codeVerifier) <= 128
            && (bool) preg_match('/\A[A-Za-z0-9_-]+\z/', $codeVerifier);
    }

    private function cacheKey(string $state): string
    {
        return self::CACHE_PREFIX.hash('sha256', $state);
    }

    private function sessionHash(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, (string) config('app.key'));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
