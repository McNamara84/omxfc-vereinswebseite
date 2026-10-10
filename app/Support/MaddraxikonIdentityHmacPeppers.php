<?php

declare(strict_types=1);

namespace App\Support;

use LogicException;

final class MaddraxikonIdentityHmacPeppers
{
    private const string FINGERPRINT_DOMAIN = 'maddraxikon-identity-hmac-pepper-fingerprint-v1';

    /**
     * @return array<string, string>
     */
    public static function parse(string $serialized): array
    {
        $configured = [];
        $entries = preg_split(
            '/\s*,\s*/',
            $serialized,
            flags: PREG_SPLIT_NO_EMPTY,
        ) ?: [];

        foreach ($entries as $entry) {
            $parts = explode(':', $entry, 2);
            $version = trim($parts[0]);
            $secret = $parts[1] ?? '';

            if (array_key_exists($version, $configured)) {
                throw new LogicException(
                    "MADDRAXIKON_IDENTITY_HMAC_PEPPERS enthält den Versionsnamen {$version} mehrfach.",
                );
            }

            $configured[$version] = trim($secret);
        }

        return $configured;
    }

    /**
     * @return array<string, string>
     */
    public static function resolve(mixed $configured): array
    {
        if (! is_array($configured) || $configured === []) {
            throw new LogicException(
                'MADDRAXIKON_IDENTITY_HMAC_PEPPERS muss vor der Kontoverknüpfung konfiguriert werden.',
            );
        }

        $peppers = [];

        foreach ($configured as $version => $secret) {
            if (! is_string($secret)) {
                throw new LogicException(
                    'MADDRAXIKON_IDENTITY_HMAC_PEPPERS enthält einen ungültigen Eintrag.',
                );
            }

            $version = trim((string) $version);
            $secret = trim($secret);

            if (
                preg_match('/^[A-Za-z0-9._-]{1,64}$/', $version) !== 1
                || $secret === ''
            ) {
                throw new LogicException(
                    'MADDRAXIKON_IDENTITY_HMAC_PEPPERS enthält einen ungültigen Eintrag.',
                );
            }

            if (str_starts_with($secret, 'raw:')) {
                $secret = substr($secret, 4);
            } elseif (str_starts_with($secret, 'base64:')) {
                $decoded = base64_decode(substr($secret, 7), true);

                if ($decoded === false) {
                    throw new LogicException(
                        "Der Identitäts-Pepper {$version} ist nicht gültig Base64-kodiert.",
                    );
                }

                $secret = $decoded;
            }

            if (strlen($secret) < 32) {
                throw new LogicException(
                    "Der Identitäts-Pepper {$version} muss mindestens 32 Byte lang sein.",
                );
            }

            $peppers[$version] = $secret;
        }

        if (array_key_first($peppers) === 'legacy-app-key') {
            throw new LogicException(
                'Der Legacy-APP_KEY darf nicht der primäre Identitäts-Pepper sein.',
            );
        }

        return $peppers;
    }

    public static function fingerprint(
        string $wikiKey,
        string $version,
        string $secret,
    ): string {
        return hash_hmac(
            'sha256',
            implode("\0", [self::FINGERPRINT_DOMAIN, $wikiKey, $version]),
            $secret,
        );
    }
}
