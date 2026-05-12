<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fantreffen_anmeldungen', 'veranstaltung_id')) {
            Schema::table('fantreffen_anmeldungen', function (Blueprint $table) {
                $table->foreignId('veranstaltung_id')->nullable()->after('id')->constrained('veranstaltungen')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('fantreffen_vip_authors', 'veranstaltung_id')) {
            Schema::table('fantreffen_vip_authors', function (Blueprint $table) {
                $table->foreignId('veranstaltung_id')->nullable()->after('id')->constrained('veranstaltungen')->nullOnDelete();
            });
        }

        $archivEventId = DB::table('veranstaltungen')->where('slug', 'maddrax-fantreffen-2026')->value('id');

        if ($archivEventId) {
            DB::table('fantreffen_anmeldungen')
                ->whereNull('veranstaltung_id')
                ->update(['veranstaltung_id' => $archivEventId]);

            DB::table('fantreffen_vip_authors')
                ->whereNull('veranstaltung_id')
                ->update(['veranstaltung_id' => $archivEventId]);
        }

        $this->dropUniqueIfExists('fantreffen_anmeldungen', 'fantreffen_anmeldungen_email_unique');
        $this->ensureIndexExists('fantreffen_anmeldungen', 'user_id', 'fantreffen_anmeldungen_user_id_index');
        $this->dropUniqueIfExists('fantreffen_anmeldungen', 'fantreffen_anmeldungen_user_id_unique');

        $this->addUniqueIfMissing(
            'fantreffen_anmeldungen',
            ['veranstaltung_id', 'email'],
            'fantreffen_anmeldungen_veranstaltung_id_email_unique'
        );
        $this->addUniqueIfMissing(
            'fantreffen_anmeldungen',
            ['veranstaltung_id', 'user_id'],
            'fantreffen_anmeldungen_veranstaltung_id_user_id_unique'
        );
    }

    public function down(): void
    {
        $this->dropUniqueIfExists('fantreffen_anmeldungen', 'fantreffen_anmeldungen_veranstaltung_id_email_unique');
        $this->dropUniqueIfExists('fantreffen_anmeldungen', 'fantreffen_anmeldungen_veranstaltung_id_user_id_unique');

        $this->addUniqueIfMissing('fantreffen_anmeldungen', 'email', 'fantreffen_anmeldungen_email_unique');
        $this->addUniqueIfMissing('fantreffen_anmeldungen', 'user_id', 'fantreffen_anmeldungen_user_id_unique');
        $this->dropIndexIfExists('fantreffen_anmeldungen', 'fantreffen_anmeldungen_user_id_index');

        if (Schema::hasColumn('fantreffen_vip_authors', 'veranstaltung_id')) {
            Schema::table('fantreffen_vip_authors', function (Blueprint $table) {
                $table->dropConstrainedForeignId('veranstaltung_id');
            });
        }

        if (Schema::hasColumn('fantreffen_anmeldungen', 'veranstaltung_id')) {
            Schema::table('fantreffen_anmeldungen', function (Blueprint $table) {
                $table->dropConstrainedForeignId('veranstaltung_id');
            });
        }
    }

    private function dropUniqueIfExists(string $tableName, string $indexName): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        } catch (\Throwable $exception) {
            if (! $this->isMissingIndexException($exception)) {
                throw $exception;
            }
        }
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        } catch (\Throwable $exception) {
            if (! $this->isMissingIndexException($exception)) {
                throw $exception;
            }
        }
    }

    private function ensureIndexExists(string $tableName, string|array $columns, string $indexName): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });
        } catch (\Throwable $exception) {
            if (! $this->isDuplicateIndexException($exception)) {
                throw $exception;
            }
        }
    }

    private function addUniqueIfMissing(string $tableName, string|array $columns, string $indexName): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                $table->unique($columns, $indexName);
            });
        } catch (\Throwable $exception) {
            if (! $this->isDuplicateIndexException($exception)) {
                throw $exception;
            }
        }
    }

    private function isMissingIndexException(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'no such index')
            || str_contains($message, 'check that column/key exists')
            || str_contains($message, 'does not exist');
    }

    private function isDuplicateIndexException(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'already exists')
            || str_contains($message, 'duplicate key name')
            || str_contains($message, 'duplicate index name');
    }
};
