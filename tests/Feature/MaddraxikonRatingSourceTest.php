<?php

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

class MaddraxikonRatingSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.maddraxikon' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('maddraxikon');

        Schema::connection('maddraxikon')->create('actor', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_id')->primary();
            $table->unsignedBigInteger('actor_user')->nullable();
        });
        Schema::connection('maddraxikon')->create('Vote', function (Blueprint $table): void {
            $table->unsignedBigInteger('vote_id')->primary();
            $table->unsignedBigInteger('vote_actor');
            $table->unsignedBigInteger('vote_page_id');
            $table->string('vote_value');
            $table->string('vote_date')->nullable();
        });
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
    }

    protected function tearDown(): void
    {
        DB::purge('maddraxikon');

        parent::tearDown();
    }

    public function test_source_returns_only_requested_user_page_pairs_and_the_latest_vote(): void
    {
        config(['maddraxikon.ratings.source_batch_size' => 1]);
        DB::connection('maddraxikon')->table('actor')->insert([
            ['actor_id' => 1, 'actor_user' => 42],
            ['actor_id' => 2, 'actor_user' => 43],
            ['actor_id' => 3, 'actor_user' => null],
        ]);
        DB::connection('maddraxikon')->table('Vote')->insert([
            [
                'vote_id' => 1,
                'vote_actor' => 1,
                'vote_page_id' => 100,
                'vote_value' => '2',
                'vote_date' => '20260809100000',
            ],
            [
                'vote_id' => 2,
                'vote_actor' => 1,
                'vote_page_id' => 100,
                'vote_value' => '5',
                'vote_date' => '20260810100000',
            ],
            [
                'vote_id' => 3,
                'vote_actor' => 2,
                'vote_page_id' => 200,
                'vote_value' => '3',
                'vote_date' => '20260810110000',
            ],
            [
                'vote_id' => 4,
                'vote_actor' => 1,
                'vote_page_id' => 200,
                'vote_value' => '4',
                'vote_date' => '20260810120000',
            ],
            [
                'vote_id' => 5,
                'vote_actor' => 3,
                'vote_page_id' => 100,
                'vote_value' => '5',
                'vote_date' => '20260810130000',
            ],
        ]);

        $ratings = app(MaddraxikonRatingSource::class)->ratingsFor([
            new MaddraxikonRatingLookup(wikiUserId: 42, pageId: 100),
            new MaddraxikonRatingLookup(wikiUserId: 43, pageId: 200),
            new MaddraxikonRatingLookup(wikiUserId: 42, pageId: 100),
        ]);

        $this->assertSame(['42:100', '43:200'], array_keys($ratings));
        $this->assertSame(5, $ratings['42:100']->rating);
        $this->assertSame('42:100', $ratings['42:100']->key());
        $this->assertSame('2026-08-10 10:00:00', $ratings['42:100']->votedAt?->format('Y-m-d H:i:s'));
        $this->assertSame(3, $ratings['43:200']->rating);
    }

    public function test_source_tolerates_missing_and_malformed_vote_dates(): void
    {
        DB::connection('maddraxikon')->table('actor')->insert([
            'actor_id' => 1,
            'actor_user' => 42,
        ]);
        DB::connection('maddraxikon')->table('Vote')->insert([
            [
                'vote_id' => 1,
                'vote_actor' => 1,
                'vote_page_id' => 100,
                'vote_value' => '4',
                'vote_date' => null,
            ],
            [
                'vote_id' => 2,
                'vote_actor' => 1,
                'vote_page_id' => 101,
                'vote_value' => '5',
                'vote_date' => 'not-a-date',
            ],
        ]);

        $ratings = app(MaddraxikonRatingSource::class)->ratingsFor([
            new MaddraxikonRatingLookup(wikiUserId: 42, pageId: 100),
            new MaddraxikonRatingLookup(wikiUserId: 42, pageId: 101),
        ]);

        $this->assertSame(4, $ratings['42:100']->rating);
        $this->assertNull($ratings['42:100']->votedAt);
        $this->assertSame(5, $ratings['42:101']->rating);
        $this->assertNull($ratings['42:101']->votedAt);
    }

    public function test_source_discards_invalid_values_without_leaking_row_data_to_logs(): void
    {
        Log::spy();
        DB::connection('maddraxikon')->table('actor')->insert([
            'actor_id' => 1,
            'actor_user' => 42,
        ]);
        DB::connection('maddraxikon')->table('Vote')->insert([
            'vote_id' => 1,
            'vote_actor' => 1,
            'vote_page_id' => 100,
            'vote_value' => '9-secret-marker',
            'vote_date' => 'not-a-date',
        ]);

        $ratings = app(MaddraxikonRatingSource::class)->ratingsFor([
            new MaddraxikonRatingLookup(wikiUserId: 42, pageId: 100),
        ]);

        $this->assertSame([], $ratings);
        Log::shouldHaveReceived('warning')->once()->with(
            'Maddraxikon-Bewertungssync hat ungültige Quelldatensätze verworfen.',
            ['invalid_count' => 1],
        );
    }

    public function test_source_handles_empty_and_invalid_lookups_without_a_query(): void
    {
        $this->assertSame([], app(MaddraxikonRatingSource::class)->ratingsFor([]));
        $this->assertSame([], app(MaddraxikonRatingSource::class)->ratingsFor([
            new MaddraxikonRatingLookup(wikiUserId: 0, pageId: 100),
            new MaddraxikonRatingLookup(wikiUserId: 42, pageId: -1),
        ]));
    }

    public function test_book_mapper_resolves_exact_titles_and_main_namespace_redirects(): void
    {
        DB::connection('maddraxikon')->table('page')->insert([
            [
                'page_id' => 10,
                'page_namespace' => 0,
                'page_title' => 'MX_1',
                'page_is_redirect' => true,
            ],
            [
                'page_id' => 11,
                'page_namespace' => 0,
                'page_title' => 'MX_001_–_Testroman',
                'page_is_redirect' => false,
            ],
            [
                'page_id' => 12,
                'page_namespace' => 1,
                'page_title' => 'MX_001_–_Testroman',
                'page_is_redirect' => false,
            ],
        ]);
        DB::connection('maddraxikon')->table('redirect')->insert([
            'rd_from' => 10,
            'rd_namespace' => 0,
            'rd_title' => 'MX_001_–_Testroman',
        ]);

        $mapping = app(MaddraxikonBookMapper::class)->resolve('MX 1');

        $this->assertNotNull($mapping);
        $this->assertSame(11, $mapping->pageId);
        $this->assertSame('MX 001 – Testroman', $mapping->pageTitle);
        $this->assertNull(app(MaddraxikonBookMapper::class)->resolve('MX 001 Testroman'));
    }

    public function test_book_mapper_rejects_redirect_loops_and_non_article_targets(): void
    {
        $this->assertNull(app(MaddraxikonBookMapper::class)->resolve("MX\n1"));

        DB::connection('maddraxikon')->table('page')->insert([
            [
                'page_id' => 20,
                'page_namespace' => 0,
                'page_title' => 'Loop_A',
                'page_is_redirect' => true,
            ],
            [
                'page_id' => 21,
                'page_namespace' => 0,
                'page_title' => 'Loop_B',
                'page_is_redirect' => true,
            ],
            [
                'page_id' => 22,
                'page_namespace' => 0,
                'page_title' => 'Outside',
                'page_is_redirect' => true,
            ],
        ]);
        DB::connection('maddraxikon')->table('redirect')->insert([
            ['rd_from' => 20, 'rd_namespace' => 0, 'rd_title' => 'Loop_B'],
            ['rd_from' => 21, 'rd_namespace' => 0, 'rd_title' => 'Loop_A'],
            ['rd_from' => 22, 'rd_namespace' => 14, 'rd_title' => 'Romane'],
        ]);

        $this->assertNull(app(MaddraxikonBookMapper::class)->resolve('Loop A'));
        $this->assertNull(app(MaddraxikonBookMapper::class)->resolve('Outside'));
    }

    public function test_book_mapper_stops_after_the_redirect_limit(): void
    {
        $pages = [];
        $redirects = [];

        foreach (range(0, 5) as $index) {
            $pages[] = [
                'page_id' => 30 + $index,
                'page_namespace' => 0,
                'page_title' => 'Chain_'.$index,
                'page_is_redirect' => true,
            ];
            $redirects[] = [
                'rd_from' => 30 + $index,
                'rd_namespace' => 0,
                'rd_title' => 'Chain_'.($index + 1),
            ];
        }

        DB::connection('maddraxikon')->table('page')->insert($pages);
        DB::connection('maddraxikon')->table('redirect')->insert($redirects);

        $this->assertNull(app(MaddraxikonBookMapper::class)->resolve('Chain 0'));
    }

    public function test_mapping_command_maps_review_books_and_skips_unreviewed_books_by_default(): void
    {
        DB::connection('maddraxikon')->table('page')->insert([
            [
                'page_id' => 100,
                'page_namespace' => 0,
                'page_title' => 'MX_100_–_Testroman',
                'page_is_redirect' => false,
            ],
            [
                'page_id' => 101,
                'page_namespace' => 0,
                'page_title' => 'MX_101_–_Ohne_Rezension',
                'page_is_redirect' => false,
            ],
        ]);
        $reviewed = Book::factory()->create([
            'maddraxikon_page_title' => 'MX 100 – Testroman',
        ]);
        $unreviewed = Book::factory()->create([
            'maddraxikon_page_title' => 'MX 101 – Ohne Rezension',
        ]);
        Review::factory()->for($reviewed)->create();

        $this->artisan('maddraxikon:map-review-books')
            ->assertSuccessful();

        $this->assertSame(100, $reviewed->refresh()->maddraxikon_page_id);
        $this->assertNotNull($reviewed->maddraxikon_page_verified_at);
        $this->assertNull($unreviewed->refresh()->maddraxikon_page_id);

        $this->artisan('maddraxikon:map-review-books', ['--all' => true])
            ->assertSuccessful();

        $this->assertSame(101, $unreviewed->refresh()->maddraxikon_page_id);
    }

    public function test_mapping_command_dry_run_does_not_write_and_missing_pages_fail_safely(): void
    {
        DB::connection('maddraxikon')->table('page')->insert([
            'page_id' => 100,
            'page_namespace' => 0,
            'page_title' => 'MX_100',
            'page_is_redirect' => false,
        ]);
        $mapped = Book::factory()->create([
            'maddraxikon_page_title' => 'MX 100',
        ]);
        $missing = Book::factory()->create([
            'maddraxikon_page_title' => 'Nicht vorhanden',
        ]);
        Review::factory()->for($mapped)->create();
        Review::factory()->for($missing)->create();

        $this->artisan('maddraxikon:map-review-books', ['--dry-run' => true])
            ->expectsOutputToContain('Keine eindeutige Maddraxikon-Seite für Buch-ID '.$missing->id)
            ->assertFailed();

        $this->assertNull($mapped->refresh()->maddraxikon_page_id);
        $this->assertNull($mapped->maddraxikon_page_verified_at);
        $this->assertNull($missing->refresh()->maddraxikon_page_id);
    }

    public function test_mapping_command_sanitizes_source_failures(): void
    {
        $book = Book::factory()->create([
            'maddraxikon_page_title' => 'MX 100',
        ]);
        Review::factory()->for($book)->create();
        $mapper = Mockery::mock(MaddraxikonBookMapper::class);
        $mapper->expects('resolve')
            ->once()
            ->andThrow(new RuntimeException('secret-database-host'));
        $this->app->instance(MaddraxikonBookMapper::class, $mapper);

        $this->artisan('maddraxikon:map-review-books')
            ->expectsOutput('Maddraxikon-Buchzuordnung fehlgeschlagen (RuntimeException).')
            ->doesntExpectOutputToContain('secret-database-host')
            ->assertFailed();
    }
}
