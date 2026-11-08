<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\MaddraxikonRatingLookup;
use App\Models\Book;
use App\Models\Review;
use App\Services\Maddraxikon\MaddraxikonBookMapper;
use App\Services\Maddraxikon\MaddraxikonRatingSource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

beforeEach(function (): void {
    config(['database.connections.maddraxikon' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'mw_', 'foreign_key_constraints' => true]]);
    DB::purge('maddraxikon');
    Schema::connection('maddraxikon')->create('Vote', function (Blueprint $table): void {
        $table->unsignedBigInteger('vote_id')->primary();
        $table->unsignedBigInteger('vote_actor')->nullable();
        $table->unsignedBigInteger('vote_user_id')->nullable();
        $table->unsignedBigInteger('vote_page_id');
        $table->string('vote_value');
        $table->string('vote_date')->nullable();
    });
    Schema::connection('maddraxikon')->create('actor', function (Blueprint $table): void {
        $table->unsignedBigInteger('actor_id')->primary();
        $table->unsignedBigInteger('actor_user')->nullable();
        $table->string('actor_name');
    });
    DB::connection('maddraxikon')->table('actor')->insert([['actor_id' => 1001, 'actor_user' => 42, 'actor_name' => 'User 42'], ['actor_id' => 1002, 'actor_user' => 43, 'actor_name' => 'User 43'], ['actor_id' => 1003, 'actor_user' => null, 'actor_name' => '192.0.2.1'], ['actor_id' => 1004, 'actor_user' => 99, 'actor_name' => 'User 99']]);
    Schema::connection('maddraxikon')->create('page', function (Blueprint $table): void {
        $table->unsignedBigInteger('page_id')->primary();
        $table->integer('page_namespace');
        $table->string('page_title');
        $table->boolean('page_is_redirect')->default(false);
    });
    Schema::connection('maddraxikon')->create('redirect', function (Blueprint $table): void {
        $table->unsignedBigInteger('rd_from');
        $table->integer('rd_namespace');
        $table->string('rd_title');
    });
});

afterEach(function (): void {
    DB::purge('maddraxikon');
});

test('source returns only requested user page pairs and the latest vote', function (): void {
    config(['maddraxikon.ratings.source_batch_size' => 1]);
    DB::connection('maddraxikon')->table('Vote')->insert([['vote_id' => 1, 'vote_actor' => 1001, 'vote_user_id' => 999, 'vote_page_id' => 100, 'vote_value' => '2', 'vote_date' => '20260809100000'], ['vote_id' => 2, 'vote_actor' => 1001, 'vote_user_id' => 999, 'vote_page_id' => 100, 'vote_value' => '5', 'vote_date' => '20260810100000'], ['vote_id' => 3, 'vote_actor' => 1002, 'vote_user_id' => 999, 'vote_page_id' => 200, 'vote_value' => '3', 'vote_date' => '20260810110000'], ['vote_id' => 4, 'vote_actor' => 1001, 'vote_user_id' => 999, 'vote_page_id' => 200, 'vote_value' => '4', 'vote_date' => '20260810120000'], ['vote_id' => 5, 'vote_actor' => 1003, 'vote_user_id' => 42, 'vote_page_id' => 100, 'vote_value' => '5', 'vote_date' => '20260810130000'], ['vote_id' => 6, 'vote_actor' => 1004, 'vote_user_id' => 42, 'vote_page_id' => 100, 'vote_value' => '1', 'vote_date' => '20260810140000']]);
    $ratings = app(MaddraxikonRatingSource::class)->ratingsFor([new MaddraxikonRatingLookup(wikiUserId: 42, pageId: 100), new MaddraxikonRatingLookup(wikiUserId: 43, pageId: 200), new MaddraxikonRatingLookup(wikiUserId: 42, pageId: 100)]);
    expect(array_keys($ratings))->toBe(['42:100', '43:200'])
        ->and($ratings['42:100']->rating)->toBe(5)
        ->and($ratings['42:100']->key())->toBe('42:100')
        ->and($ratings['42:100']->votedAt?->format('Y-m-d H:i:s'))->toBe('2026-08-10 10:00:00')
        ->and($ratings['43:200']->rating)->toBe(3);
});

test('source tolerates missing and malformed vote dates', function (): void {
    DB::connection('maddraxikon')->table('Vote')->insert([['vote_id' => 1, 'vote_actor' => 1001, 'vote_user_id' => 999, 'vote_page_id' => 100, 'vote_value' => '4', 'vote_date' => null], ['vote_id' => 2, 'vote_actor' => 1001, 'vote_user_id' => 999, 'vote_page_id' => 101, 'vote_value' => '5', 'vote_date' => 'not-a-date']]);
    $ratings = app(MaddraxikonRatingSource::class)->ratingsFor([new MaddraxikonRatingLookup(wikiUserId: 42, pageId: 100), new MaddraxikonRatingLookup(wikiUserId: 42, pageId: 101)]);
    expect($ratings['42:100']->rating)->toBe(4)
        ->and($ratings['42:100']->votedAt)->toBeNull()
        ->and($ratings['42:101']->rating)->toBe(5)
        ->and($ratings['42:101']->votedAt)->toBeNull();
});

test('source discards invalid values without leaking row data to logs', function (): void {
    Log::spy();
    DB::connection('maddraxikon')->table('Vote')->insert(['vote_id' => 1, 'vote_actor' => 1001, 'vote_user_id' => 999, 'vote_page_id' => 100, 'vote_value' => '9-secret-marker', 'vote_date' => 'not-a-date']);
    $ratings = app(MaddraxikonRatingSource::class)->ratingsFor([new MaddraxikonRatingLookup(wikiUserId: 42, pageId: 100)]);
    expect($ratings)->toBe([]);
    Log::shouldHaveReceived('warning')->once()->with('Maddraxikon-Bewertungssync hat ungültige Quelldatensätze verworfen.', ['invalid_count' => 1]);
});

test('source handles empty and invalid lookups without a query', function (): void {
    expect(app(MaddraxikonRatingSource::class)->ratingsFor([]))->toBe([])
        ->and(app(MaddraxikonRatingSource::class)->ratingsFor([new MaddraxikonRatingLookup(wikiUserId: 0, pageId: 100), new MaddraxikonRatingLookup(wikiUserId: 42, pageId: -1)]))->toBe([]);
});

test('book mapper resolves exact titles and main namespace redirects', function (): void {
    DB::connection('maddraxikon')->table('page')->insert([['page_id' => 10, 'page_namespace' => 0, 'page_title' => 'MX_1', 'page_is_redirect' => true], ['page_id' => 11, 'page_namespace' => 0, 'page_title' => 'MX_001_–_Testroman', 'page_is_redirect' => false], ['page_id' => 12, 'page_namespace' => 1, 'page_title' => 'MX_001_–_Testroman', 'page_is_redirect' => false]]);
    DB::connection('maddraxikon')->table('redirect')->insert(['rd_from' => 10, 'rd_namespace' => 0, 'rd_title' => 'MX_001_–_Testroman']);
    $mapping = app(MaddraxikonBookMapper::class)->resolve('MX 1');
    expect($mapping)->not->toBeNull()
        ->and($mapping->pageId)->toBe(11)
        ->and($mapping->pageTitle)->toBe('MX 001 – Testroman')
        ->and(app(MaddraxikonBookMapper::class)->resolve('MX 001 Testroman'))->toBeNull();
});

test('book mapper rejects redirect loops and non article targets', function (): void {
    expect(app(MaddraxikonBookMapper::class)->resolve("MX\n1"))->toBeNull();
    DB::connection('maddraxikon')->table('page')->insert([['page_id' => 20, 'page_namespace' => 0, 'page_title' => 'Loop_A', 'page_is_redirect' => true], ['page_id' => 21, 'page_namespace' => 0, 'page_title' => 'Loop_B', 'page_is_redirect' => true], ['page_id' => 22, 'page_namespace' => 0, 'page_title' => 'Outside', 'page_is_redirect' => true]]);
    DB::connection('maddraxikon')->table('redirect')->insert([['rd_from' => 20, 'rd_namespace' => 0, 'rd_title' => 'Loop_B'], ['rd_from' => 21, 'rd_namespace' => 0, 'rd_title' => 'Loop_A'], ['rd_from' => 22, 'rd_namespace' => 14, 'rd_title' => 'Romane']]);
    expect(app(MaddraxikonBookMapper::class)->resolve('Loop A'))->toBeNull()
        ->and(app(MaddraxikonBookMapper::class)->resolve('Outside'))->toBeNull();
});

test('book mapper stops after the redirect limit', function (): void {
    $pages = [];
    $redirects = [];
    foreach (range(0, 5) as $index) {
        $pages[] = ['page_id' => 30 + $index, 'page_namespace' => 0, 'page_title' => 'Chain_'.$index, 'page_is_redirect' => true];
        $redirects[] = ['rd_from' => 30 + $index, 'rd_namespace' => 0, 'rd_title' => 'Chain_'.($index + 1)];
    }
    DB::connection('maddraxikon')->table('page')->insert($pages);
    DB::connection('maddraxikon')->table('redirect')->insert($redirects);
    expect(app(MaddraxikonBookMapper::class)->resolve('Chain 0'))->toBeNull();
});

test('mapping command maps review books and skips unreviewed books by default', function (): void {
    DB::connection('maddraxikon')->table('page')->insert([['page_id' => 100, 'page_namespace' => 0, 'page_title' => 'MX_100_–_Testroman', 'page_is_redirect' => false], ['page_id' => 101, 'page_namespace' => 0, 'page_title' => 'MX_101_–_Ohne_Rezension', 'page_is_redirect' => false]]);
    $reviewed = Book::factory()->create(['maddraxikon_page_title' => 'MX 100 – Testroman']);
    $unreviewed = Book::factory()->create(['maddraxikon_page_title' => 'MX 101 – Ohne Rezension']);
    Review::factory()->for($reviewed)->create();
    $this->artisan('maddraxikon:map-review-books')->assertSuccessful();
    expect($reviewed->refresh()->maddraxikon_page_id)->toBe(100)
        ->and($reviewed->maddraxikon_page_verified_at)->not->toBeNull()
        ->and($unreviewed->refresh()->maddraxikon_page_id)->toBeNull();
    $this->artisan('maddraxikon:map-review-books', ['--all' => true])->assertSuccessful();
    expect($unreviewed->refresh()->maddraxikon_page_id)->toBe(101);
});

test('mapping command dry run does not write and missing pages fail safely', function (): void {
    DB::connection('maddraxikon')->table('page')->insert(['page_id' => 100, 'page_namespace' => 0, 'page_title' => 'MX_100', 'page_is_redirect' => false]);
    $mapped = Book::factory()->create(['maddraxikon_page_title' => 'MX 100']);
    $missing = Book::factory()->create(['maddraxikon_page_title' => 'Nicht vorhanden']);
    Review::factory()->for($mapped)->create();
    Review::factory()->for($missing)->create();
    $this->artisan('maddraxikon:map-review-books', ['--dry-run' => true])->expectsOutputToContain('Keine eindeutige Maddraxikon-Seite für Buch-ID '.$missing->id)->assertFailed();
    expect($mapped->refresh()->maddraxikon_page_id)->toBeNull()
        ->and($mapped->maddraxikon_page_verified_at)->toBeNull()
        ->and($missing->refresh()->maddraxikon_page_id)->toBeNull();
});

test('mapping command sanitizes source failures', function (): void {
    $book = Book::factory()->create(['maddraxikon_page_title' => 'MX 100']);
    Review::factory()->for($book)->create();
    $mapper = Mockery::mock(MaddraxikonBookMapper::class);
    $mapper->expects('resolve')->once()->andThrow(new RuntimeException('secret-database-host'));
    $this->app->instance(MaddraxikonBookMapper::class, $mapper);
    $this->artisan('maddraxikon:map-review-books')->expectsOutput('Maddraxikon-Buchzuordnung fehlgeschlagen (RuntimeException).')->doesntExpectOutputToContain('secret-database-host')->assertFailed();
});
