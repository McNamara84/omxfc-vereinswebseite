<?php

use App\Support\MaddraxikonIdentityHmacPeppers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn(
            'maddraxikon_identity_tombstones',
            'hash_key_fingerprint',
        )) {
            Schema::table(
                'maddraxikon_identity_tombstones',
                function (Blueprint $table): void {
                    $table->char('hash_key_fingerprint', 64)
                        ->nullable()
                        ->after('hash_key_version');
                },
            );
        }

        if (! DB::table('maddraxikon_identity_tombstones')->exists()) {
            return;
        }

        $peppers = MaddraxikonIdentityHmacPeppers::resolve(
            config('maddraxikon.identity_hmac_peppers', []),
        );
        $tombstones = DB::table('maddraxikon_identity_tombstones')
            ->select(['id', 'wiki_key', 'hash_key_version'])
            ->orderBy('id')
            ->get();

        foreach ($tombstones as $tombstone) {
            $version = $tombstone->hash_key_version === null
                ? 'legacy-app-key'
                : (string) $tombstone->hash_key_version;

            if (! array_key_exists($version, $peppers)) {
                throw new LogicException(
                    "Der HMAC-Schlüssel für die bestehende Tombstone-Version {$version} fehlt.",
                );
            }
        }

        foreach ($tombstones as $tombstone) {
            $wikiKey = (string) $tombstone->wiki_key;
            $version = $tombstone->hash_key_version === null
                ? 'legacy-app-key'
                : (string) $tombstone->hash_key_version;

            DB::table('maddraxikon_identity_tombstones')
                ->where('id', $tombstone->id)
                ->update([
                    'hash_key_fingerprint' => MaddraxikonIdentityHmacPeppers::fingerprint(
                        $wikiKey,
                        $version,
                        $peppers[$version],
                    ),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(
            'maddraxikon_identity_tombstones',
            'hash_key_fingerprint',
        )) {
            Schema::table(
                'maddraxikon_identity_tombstones',
                function (Blueprint $table): void {
                    $table->dropColumn('hash_key_fingerprint');
                },
            );
        }
    }
};
