<?php

namespace App\Services\Maddraxikon;

use App\Exceptions\MaddraxikonApiException;

final class MaddraxikonApiRequestGuard
{
    public function trustedApiUrl(): string
    {
        $apiUrl = (string) config(
            'maddraxikon.api_url',
            'https://de.maddraxikon.com/api.php',
        );
        $baseUrl = (string) config(
            'maddraxikon.base_url',
            'https://de.maddraxikon.com',
        );
        $apiParts = parse_url($apiUrl);
        $baseParts = parse_url($baseUrl);

        if (
            ! is_array($apiParts)
            || ! is_array($baseParts)
            || strtolower((string) ($apiParts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($baseParts['scheme'] ?? '')) !== 'https'
            || ! isset($apiParts['host'], $baseParts['host'])
            || isset($apiParts['query'])
            || isset($apiParts['fragment'])
            || isset($apiParts['user'])
            || isset($apiParts['pass'])
            || isset($baseParts['query'])
            || isset($baseParts['fragment'])
            || isset($baseParts['user'])
            || isset($baseParts['pass'])
            || ! $this->sameOrigin($apiParts, $baseParts)
        ) {
            throw new MaddraxikonApiException(
                'Die Maddraxikon-API-URL ist nicht sicher konfiguriert.',
            );
        }

        return $apiUrl;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $allowedKeys
     * @param  array<string, true>  $seen
     * @return array<string, int|string>
     */
    public function nextContinuation(
        array $payload,
        array $allowedKeys,
        array &$seen,
    ): array {
        $continuation = $payload['continue'] ?? null;

        if ($continuation === null) {
            return [];
        }

        if (! is_array($continuation) || $continuation === []) {
            throw new MaddraxikonApiException(
                'Die Maddraxikon-API lieferte einen ungültigen Fortsetzungsmarker.',
            );
        }

        $normalized = [];

        foreach ($continuation as $key => $value) {
            if (
                ! is_string($key)
                || ! in_array($key, $allowedKeys, true)
                || (! is_string($value) && ! is_int($value))
            ) {
                throw new MaddraxikonApiException(
                    'Die Maddraxikon-API lieferte einen ungültigen Fortsetzungsmarker.',
                );
            }

            $normalized[$key] = $value;
        }

        ksort($normalized);
        $fingerprint = hash(
            'sha256',
            json_encode($normalized, JSON_THROW_ON_ERROR),
        );

        if (isset($seen[$fingerprint])) {
            throw new MaddraxikonApiException(
                'Die Maddraxikon-API wiederholte einen Fortsetzungsmarker.',
            );
        }

        $seen[$fingerprint] = true;

        return $normalized;
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
        $defaultPorts = ['http' => 80, 'https' => 443];
        $leftPort = $left['port'] ?? ($defaultPorts[$leftScheme] ?? null);
        $rightPort = $right['port'] ?? ($defaultPorts[$rightScheme] ?? null);

        return $leftScheme !== ''
            && $rightScheme !== ''
            && $leftHost !== ''
            && $rightHost !== ''
            && $leftScheme === $rightScheme
            && strcasecmp($leftHost, $rightHost) === 0
            && $leftPort === $rightPort;
    }
}
