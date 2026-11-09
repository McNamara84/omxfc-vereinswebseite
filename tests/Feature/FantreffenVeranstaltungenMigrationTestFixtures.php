<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

trait FantreffenVeranstaltungenMigrationTestFixtures
{
    use RefreshDatabase;

    private function dropIndexIfExists(string $tableName, string $indexName, bool $unique = false): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName, $unique) {
                if ($unique) {
                    $table->dropUnique($indexName);

                    return;
                }
                $table->dropIndex($indexName);
            });
        } catch (\Throwable) {
        }
    }
}
