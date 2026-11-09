<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\MaddraxikonRatingLookup;
use App\Services\Maddraxikon\MaddraxikonRatingSource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonRatingSourceMariaDbTestFixtures::class);

beforeEach(function (): void {
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
});

afterEach(function (): void {
    try {
        if ($this->sourceTablesCreated) {
            Schema::connection('maddraxikon')->dropIfExists('Vote');
            Schema::connection('maddraxikon')->dropIfExists('vote');
            Schema::connection('maddraxikon')->dropIfExists('actor');
        }
        DB::purge('maddraxikon');
    } finally {
    }
});

test('source reads case sensitive uppercase vote table', function (): void {
    DB::connection('maddraxikon')->table('actor')->insert([['actor_id' => 1001, 'actor_user' => 42, 'actor_name' => 'Expected actor'], ['actor_id' => 1002, 'actor_user' => 99, 'actor_name' => 'Legacy-field decoy']]);
    DB::connection('maddraxikon')->table('Vote')->insert([['vote_id' => 1, 'vote_actor' => 1001, 'vote_user_id' => 99, 'vote_page_id' => 100, 'vote_value' => '5', 'vote_date' => '20260810100000'], ['vote_id' => 2, 'vote_actor' => 1002, 'vote_user_id' => 42, 'vote_page_id' => 100, 'vote_value' => '1', 'vote_date' => '20260810110000']]);
    DB::connection('maddraxikon')->table('vote')->insert(['vote_id' => 1, 'vote_actor' => 1001, 'vote_user_id' => 99, 'vote_page_id' => 100, 'vote_value' => '1', 'vote_date' => '20260810100000']);
    $ratings = app(MaddraxikonRatingSource::class)->ratingsFor([new MaddraxikonRatingLookup(wikiUserId: 42, pageId: 100)]);
    expect($ratings['42:100']->rating)->toBe(5);
});
