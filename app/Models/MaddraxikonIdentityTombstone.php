<?php

namespace App\Models;

use App\Support\MaddraxikonIdentityHmacPeppers;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class MaddraxikonIdentityTombstone extends Model
{
    protected $fillable = [
        'wiki_key',
        'hash_key_version',
        'hash_key_fingerprint',
        'oauth_subject_hash',
        'wiki_user_id_hash',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'retired_at' => 'datetime',
        ];
    }

    public static function retire(MaddraxikonAccountLink $link): self
    {
        static::assertStoredHashKeyVersionsAreConfigured($link->wiki_key);

        $peppers = static::peppers();
        $version = (string) array_key_first($peppers);
        $pepper = $peppers[$version];
        $tombstone = static::query()->firstOrCreate([
            'wiki_key' => $link->wiki_key,
            'oauth_subject_hash' => static::identityHash(
                $link->wiki_key,
                'oauth-subject',
                $link->oauth_subject,
                $pepper,
            ),
            'wiki_user_id_hash' => static::identityHash(
                $link->wiki_key,
                'wiki-user-id',
                (string) $link->wiki_user_id,
                $pepper,
            ),
        ], [
            'hash_key_version' => $version,
            'hash_key_fingerprint' => MaddraxikonIdentityHmacPeppers::fingerprint(
                $link->wiki_key,
                $version,
                $pepper,
            ),
            'retired_at' => now(),
        ]);

        static::assertStoredHashKeyVersionsAreConfigured($link->wiki_key);

        return $tombstone;
    }

    public static function oauthSubjectHash(string $wikiKey, string $oauthSubject): string
    {
        return static::identityHash(
            $wikiKey,
            'oauth-subject',
            $oauthSubject,
            static::currentPepper(),
        );
    }

    public static function wikiUserIdHash(string $wikiKey, int $wikiUserId): string
    {
        return static::identityHash(
            $wikiKey,
            'wiki-user-id',
            (string) $wikiUserId,
            static::currentPepper(),
        );
    }

    /**
     * Every configured key is checked so tombstones remain effective after a
     * key rotation. The first configured key is used for new hashes.
     *
     * @return list<string>
     */
    public static function oauthSubjectHashes(
        string $wikiKey,
        string $oauthSubject,
    ): array {
        return static::identityHashes($wikiKey, 'oauth-subject', $oauthSubject);
    }

    /**
     * @return list<string>
     */
    public static function wikiUserIdHashes(string $wikiKey, int $wikiUserId): array
    {
        return static::identityHashes($wikiKey, 'wiki-user-id', (string) $wikiUserId);
    }

    public static function assertStoredHashKeyVersionsAreConfigured(
        string $wikiKey,
    ): void {
        $peppers = static::peppers();
        $configuredVersions = array_map(
            static fn (int|string $version): string => (string) $version,
            array_keys($peppers),
        );
        $storedKeys = static::query()
            ->where('wiki_key', $wikiKey)
            ->select(['hash_key_version', 'hash_key_fingerprint'])
            ->distinct()
            ->get();
        $storedVersions = $storedKeys
            ->map(static fn (self $tombstone): string => $tombstone->hash_key_version === null
                ? 'legacy-app-key'
                : (string) $tombstone->hash_key_version)
            ->unique()
            ->values()
            ->all();
        $missingVersions = array_values(array_diff(
            $storedVersions,
            $configuredVersions,
        ));

        if ($missingVersions !== []) {
            throw new LogicException(
                'Für bestehende Maddraxikon-Identitätssperren fehlen HMAC-Schlüsselversionen: '.
                implode(', ', $missingVersions),
            );
        }

        $fingerprintMismatches = [];

        foreach ($storedKeys as $storedKey) {
            $version = $storedKey->hash_key_version === null
                ? 'legacy-app-key'
                : (string) $storedKey->hash_key_version;
            $storedFingerprint = $storedKey->hash_key_fingerprint;
            $expectedFingerprint = MaddraxikonIdentityHmacPeppers::fingerprint(
                $wikiKey,
                $version,
                $peppers[$version],
            );

            if (
                ! is_string($storedFingerprint)
                || strlen($storedFingerprint) !== 64
                || ! hash_equals($expectedFingerprint, $storedFingerprint)
            ) {
                $fingerprintMismatches[] = $version;
            }
        }

        if ($fingerprintMismatches !== []) {
            throw new LogicException(
                'Der konfigurierte Identitäts-Pepper stimmt nicht mit bestehenden '
                .'Maddraxikon-Identitätssperren überein. Versionsnamen dürfen '
                .'nach ihrer ersten Verwendung niemals einem anderen Geheimnis '
                .'zugeordnet werden: '.implode(', ', array_unique($fingerprintMismatches)),
            );
        }
    }

    public static function currentHashKeyVersion(): string
    {
        return (string) array_key_first(static::peppers());
    }

    /**
     * @return list<string>
     */
    private static function identityHashes(
        string $wikiKey,
        string $type,
        string $value,
    ): array {
        return array_values(array_unique(array_map(
            fn (string $pepper): string => static::identityHash(
                $wikiKey,
                $type,
                $value,
                $pepper,
            ),
            static::peppers(),
        )));
    }

    private static function identityHash(
        string $wikiKey,
        string $type,
        string $value,
        string $pepper,
    ): string {
        return hash_hmac(
            'sha256',
            implode("\0", [$wikiKey, $type, $value]),
            $pepper,
        );
    }

    private static function currentPepper(): string
    {
        return array_values(static::peppers())[0];
    }

    /**
     * @return array<string, string>
     */
    private static function peppers(): array
    {
        return MaddraxikonIdentityHmacPeppers::resolve(
            config('maddraxikon.identity_hmac_peppers', []),
        );
    }
}
