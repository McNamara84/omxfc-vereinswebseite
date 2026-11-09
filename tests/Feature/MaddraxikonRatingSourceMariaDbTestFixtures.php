<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

trait MaddraxikonRatingSourceMariaDbTestFixtures
{
    use RefreshDatabase;

    private const PREFIX = 'rating_case_';

    private bool $sourceTablesCreated = false;

    private function requireSafeCaseSensitiveMariaDb(): void
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Der Tabellen-Großschreibungstest benötigt MariaDB.');
        }
        if ($database !== 'omxfc_maddraxikon_test') {
            throw new RuntimeException("Unsichere Testdatenbank verweigert: {$database}.");
        }
        $version = (string) (DB::selectOne('SELECT VERSION() AS version')->version ?? '');
        if (! str_contains($version, 'MariaDB')) {
            $this->markTestSkipped('Der Tabellen-Großschreibungstest läuft nur auf MariaDB.');
        }
        $lowerCaseTableNames = (int) (DB::selectOne('SELECT @@lower_case_table_names AS value')->value ?? 1);
        if ($lowerCaseTableNames !== 0) {
            $this->markTestSkipped('Die MariaDB-Instanz unterscheidet Tabellennamen nicht nach Großschreibung.');
        }
    }

    private function createVoteColumns(Blueprint $table): void
    {
        $table->unsignedBigInteger('vote_id')->primary();
        $table->unsignedBigInteger('vote_actor')->nullable();
        $table->unsignedBigInteger('vote_user_id')->nullable();
        $table->unsignedBigInteger('vote_page_id');
        $table->string('vote_value');
        $table->string('vote_date')->nullable();
    }
}
