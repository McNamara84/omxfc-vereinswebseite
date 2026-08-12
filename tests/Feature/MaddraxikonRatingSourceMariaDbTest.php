<?php

namespace Tests\Feature;

use App\Data\MaddraxikonRatingLookup;
use App\Services\Maddraxikon\MaddraxikonRatingSource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class MaddraxikonRatingSourceMariaDbTest extends TestCase
{
    use RefreshDatabase;

    private const PREFIX = 'rating_case_';

    private bool $sourceTablesCreated = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireSafeCaseSensitiveMariaDb();
        $defaultConnection = (string) config('database.default');
        $sourceConfig = config("database.connections.{$defaultConnection}");

        if (! is_array($sourceConfig)) {
            throw new RuntimeException('MariaDB-Testverbindung ist nicht konfiguriert.');
        }

        $sourceConfig['prefix'] = self::PREFIX;
        config(['database.connections.maddraxikon' => $sourceConfig]);
        DB::purge('maddraxikon');

        Schema::connection('maddraxikon')->dropIfExists('Vote');
        Schema::connection('maddraxikon')->dropIfExists('vote');
        Schema::connection('maddraxikon')->dropIfExists('actor');
        $this->sourceTablesCreated = true;

        Schema::connection('maddraxikon')->create('actor', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_id')->primary();
            $table->unsignedBigInteger('actor_user')->nullable();
            $table->string('actor_name');
        });
        Schema::connection('maddraxikon')->create('Vote', function (Blueprint $table): void {
            $this->createVoteColumns($table);
        });
        Schema::connection('maddraxikon')->create('vote', function (Blueprint $table): void {
            $this->createVoteColumns($table);
        });
    }

    protected function tearDown(): void
    {
        try {
            if ($this->sourceTablesCreated) {
                Schema::connection('maddraxikon')->dropIfExists('Vote');
                Schema::connection('maddraxikon')->dropIfExists('vote');
                Schema::connection('maddraxikon')->dropIfExists('actor');
            }

            DB::purge('maddraxikon');
        } finally {
            parent::tearDown();
        }
    }

    public function test_source_reads_case_sensitive_uppercase_vote_table(): void
    {
        DB::connection('maddraxikon')->table('actor')->insert([
            ['actor_id' => 1001, 'actor_user' => 42, 'actor_name' => 'Expected actor'],
            ['actor_id' => 1002, 'actor_user' => 99, 'actor_name' => 'Legacy-field decoy'],
        ]);
        DB::connection('maddraxikon')->table('Vote')->insert([
            [
                'vote_id' => 1,
                'vote_actor' => 1001,
                'vote_user_id' => 99,
                'vote_page_id' => 100,
                'vote_value' => '5',
                'vote_date' => '20260810100000',
            ],
            [
                'vote_id' => 2,
                'vote_actor' => 1002,
                'vote_user_id' => 42,
                'vote_page_id' => 100,
                'vote_value' => '1',
                'vote_date' => '20260810110000',
            ],
        ]);
        DB::connection('maddraxikon')->table('vote')->insert([
            'vote_id' => 1,
            'vote_actor' => 1001,
            'vote_user_id' => 99,
            'vote_page_id' => 100,
            'vote_value' => '1',
            'vote_date' => '20260810100000',
        ]);

        $ratings = app(MaddraxikonRatingSource::class)->ratingsFor([
            new MaddraxikonRatingLookup(wikiUserId: 42, pageId: 100),
        ]);

        $this->assertSame(5, $ratings['42:100']->rating);
    }

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

        $lowerCaseTableNames = (int) (
            DB::selectOne('SELECT @@lower_case_table_names AS value')->value ?? 1
        );

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
