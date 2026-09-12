<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use App\Models\Book;
use App\Services\MaddraxDataService;
use App\Services\Maddraxikon\MaddraxikonBookImporter;
use App\Services\Maddraxikon\MaddraxikonCandidateStore;
use App\Services\Maddraxikon\MaddraxikonRefreshCoordinator;
use App\Services\Maddraxikon\MaddraxikonSnapshotRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class MaddraxikonRefreshTest extends TestCase
{
    use RefreshDatabase;

    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testStoragePath = base_path('storage/testing-maddrax-refresh');
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath.'/app/private');
        File::ensureDirectoryExists($this->testStoragePath.'/framework/views');
        config([
            'filesystems.disks.private.root' => $this->testStoragePath.'/app/private',
            'maddraxikon.http.attempts' => 3,
            'maddraxikon.http.retry_delay_ms' => 0,
            'maddraxikon.http.retry_max_delay_ms' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStoragePath);
        Mockery::close();
        parent::tearDown();
    }

    public function test_dry_run_creates_immutable_candidate_without_changing_active_state(): void
    {
        $category = 'https://de.maddraxikon.com/index.php?title=Kategorie:Maddrax-Heftromane';
        Http::fake([
            $category => Http::response('<div id="mw-pages"><a href="/wiki/MX_1">MX 1</a></div>'),
            'https://de.maddraxikon.com/wiki/MX_1' => Http::response($this->articleHtml(1, 'Euree')),
        ]);
        Cache::put('maddrax_series_maddrax', ['old']);

        $this->artisan('books:refresh', ['--series' => 'maddrax', '--dry-run' => true])
            ->expectsOutputToContain('Dry-Run erfolgreich')
            ->assertSuccessful();

        $this->assertFalse(Storage::disk('private')->exists('maddrax.json'));
        $this->assertSame(['old'], Cache::get('maddrax_series_maddrax'));
        $this->assertSame(0, Book::query()->where('type', BookType::MaddraxDieDunkleZukunftDerErde)->count());
        $this->assertNull(app(MaddraxikonSnapshotRepository::class)->activeId(
            BookType::MaddraxDieDunkleZukunftDerErde,
        ));
        $manifests = File::glob(Storage::disk('private')->path('maddrax-candidates/*/manifest.json'));
        $this->assertCount(1, $manifests);
        $manifest = json_decode(File::get($manifests[0]), true);
        $this->assertSame(1, $manifest['series']['maddrax']['count']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $manifest['series']['maddrax']['sha256']);
    }

    public function test_promotion_updates_database_snapshot_books_and_cache_together(): void
    {
        $old = [$this->row(1, 'Alter Zyklus', 'Alter Titel')];
        $new = [$this->row(1, 'Euree', 'Neuer Titel')];
        Storage::disk('private')->put('maddrax.json', json_encode($old));
        Book::create([
            'roman_number' => 1,
            'title' => 'Alter Titel',
            'author' => 'Alt',
            'cycle' => 'Alter Zyklus',
        ]);
        Cache::put('maddrax_series_maddrax', $old);
        $candidate = app(MaddraxikonCandidateStore::class)->stage(['maddrax' => $new]);

        $counts = app(MaddraxikonRefreshCoordinator::class)->promote($candidate['id']);

        $this->assertSame(['maddrax' => 1], $counts);
        $this->assertDatabaseHas('books', [
            'roman_number' => 1,
            'title' => 'Neuer Titel',
            'cycle' => 'Euree',
            'type' => BookType::MaddraxDieDunkleZukunftDerErde->value,
        ]);
        $snapshot = app(MaddraxikonSnapshotRepository::class)->activeDataset(
            BookType::MaddraxDieDunkleZukunftDerErde,
        );
        $this->assertNotNull($snapshot);
        $this->assertSame($candidate['id'], $snapshot['id']);
        $this->assertSame($new, $snapshot['rows']);
        $this->assertSame($old, json_decode(Storage::disk('private')->get('maddrax.json'), true));
        $this->assertFalse(Storage::disk('private')->exists('maddrax-candidates/'.$candidate['id']));
        $this->assertNull(Cache::get('maddrax_series_maddrax'));
        $this->assertSame('Neuer Titel', app(MaddraxDataService::class)->getMaddraxRomane()->first()['titel']);
    }

    public function test_import_failure_rolls_back_snapshot_pointer_books_and_cache(): void
    {
        $old = [$this->row(1, 'Alter Zyklus', 'Alter Titel')];
        $new = [$this->row(1, 'Euree', 'Neuer Titel')];
        Storage::disk('private')->put('maddrax.json', json_encode($old));
        $this->activateSnapshot('11111111-1111-4111-8111-111111111111', ['maddrax' => $old]);
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Alter Titel',
            'author' => 'Alt',
            'cycle' => 'Alter Zyklus',
        ]);
        Cache::put('maddrax_series_maddrax', $old);
        $candidate = app(MaddraxikonCandidateStore::class)->stage(['maddrax' => $new]);
        $importer = Mockery::mock(MaddraxikonBookImporter::class);
        $importer->shouldReceive('import')->once()->andReturnUsing(function (): void {
            Book::query()->update(['title' => 'Nicht committen']);

            throw new \RuntimeException('Import kaputt');
        });
        $coordinator = new MaddraxikonRefreshCoordinator(
            app(MaddraxikonCandidateStore::class),
            $importer,
            app(MaddraxDataService::class),
            app(MaddraxikonSnapshotRepository::class),
        );

        try {
            $coordinator->promote($candidate['id']);
            $this->fail('Expected import failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Import kaputt', $exception->getMessage());
        }

        $this->assertSame($old, json_decode(Storage::disk('private')->get('maddrax.json'), true));
        $this->assertSame('Alter Titel', $book->fresh()->title);
        $this->assertSame(
            '11111111-1111-4111-8111-111111111111',
            app(MaddraxikonSnapshotRepository::class)->activeId(BookType::MaddraxDieDunkleZukunftDerErde),
        );
        $this->assertTrue(Storage::disk('private')->exists('maddrax-candidates/'.$candidate['id']));
        $this->assertSame($old, Cache::get('maddrax_series_maddrax'));
    }

    public function test_failure_after_pointer_update_rolls_back_to_previous_snapshot(): void
    {
        $old = [$this->row(1, 'Alter Zyklus', 'Alter Titel')];
        $new = [$this->row(1, 'Euree', 'Neuer Titel')];
        $oldJson = json_encode($old);
        Storage::disk('private')->put('maddrax.json', $oldJson);
        $oldSnapshotId = '22222222-2222-4222-8222-222222222222';
        $this->activateSnapshot($oldSnapshotId, ['maddrax' => $old]);
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Alter Titel',
            'author' => 'Alt',
            'cycle' => 'Alter Zyklus',
        ]);
        Cache::put('maddrax_series_maddrax', $old);
        $candidate = app(MaddraxikonCandidateStore::class)->stage(['maddrax' => $new]);
        $realSnapshots = app(MaddraxikonSnapshotRepository::class);
        $snapshots = Mockery::mock(MaddraxikonSnapshotRepository::class);
        $snapshots->shouldReceive('storeAndActivate')
            ->once()
            ->andReturnUsing(function (string $id, array $json) use ($realSnapshots): void {
                $realSnapshots->storeAndActivate($id, $json);

                throw new MaddraxikonCrawlException('Aktivierung kaputt');
            });
        $coordinator = new MaddraxikonRefreshCoordinator(
            app(MaddraxikonCandidateStore::class),
            app(MaddraxikonBookImporter::class),
            app(MaddraxDataService::class),
            $snapshots,
        );

        try {
            $coordinator->promote($candidate['id']);
            $this->fail('Expected snapshot activation failure.');
        } catch (MaddraxikonCrawlException $exception) {
            $this->assertSame('Aktivierung kaputt', $exception->getMessage());
        }

        $this->assertSame($oldJson, Storage::disk('private')->get('maddrax.json'));
        $this->assertSame('Alter Titel', $book->fresh()->title);
        $this->assertSame('Alter Zyklus', $book->fresh()->cycle);
        $this->assertSame(
            $oldSnapshotId,
            $realSnapshots->activeId(BookType::MaddraxDieDunkleZukunftDerErde),
        );
        $this->assertDatabaseMissing('maddraxikon_book_snapshots', [
            'snapshot_id' => $candidate['id'],
        ]);
        $this->assertSame($old, Cache::get('maddrax_series_maddrax'));
    }

    public function test_staging_prunes_expired_candidates_but_keeps_current_dry_runs(): void
    {
        $store = app(MaddraxikonCandidateStore::class);
        $expired = $store->stage(['maddrax' => [$this->row(1, 'Euree', 'Alt')]]);
        $manifestPath = 'maddrax-candidates/'.$expired['id'].'/manifest.json';
        $manifest = json_decode(Storage::disk('private')->get($manifestPath), true);
        $manifest['expires_at'] = now()->subMinute()->toIso8601String();
        Storage::disk('private')->put($manifestPath, json_encode($manifest));

        $current = $store->stage(['maddrax' => [$this->row(1, 'Euree', 'Neu')]]);

        $this->assertFalse(Storage::disk('private')->exists('maddrax-candidates/'.$expired['id']));
        $this->assertTrue(Storage::disk('private')->exists('maddrax-candidates/'.$current['id']));
        $this->assertSame(0, $store->pruneExpired());
    }

    public function test_tampered_candidate_is_rejected(): void
    {
        $candidate = app(MaddraxikonCandidateStore::class)->stage([
            'maddrax' => [$this->row(1, 'Euree', 'Titel')],
        ]);
        Storage::disk('private')->put(
            'maddrax-candidates/'.$candidate['id'].'/maddrax.json',
            json_encode([$this->row(1, 'Manipuliert', 'Titel')]),
        );

        $this->expectException(MaddraxikonCrawlException::class);
        $this->expectExceptionMessage('Hashprüfung');

        app(MaddraxikonRefreshCoordinator::class)->promote($candidate['id']);
    }

    public function test_failed_followup_page_keeps_last_valid_snapshot_byte_identical(): void
    {
        $category = 'https://de.maddraxikon.com/index.php?title=Kategorie:Maddrax-Heftromane';
        $page2 = $category.'&pagefrom=201';
        $oldJson = json_encode([$this->row(1, 'Euree', 'Alt')]);
        Storage::disk('private')->put('maddrax.json', $oldJson);
        Book::create([
            'roman_number' => 1,
            'title' => 'Alt',
            'author' => 'Autor',
            'cycle' => 'Euree',
        ]);
        Http::fakeSequence()
            ->push('<meta charset="UTF-8"><div id="mw-pages"><a href="/wiki/MX_1">MX 1</a><a href="'.$page2.'">nächste Seite</a></div>')
            ->push('maintenance', 503)
            ->push('maintenance', 503)
            ->push('maintenance', 503);

        $this->artisan('books:refresh', ['--series' => 'maddrax'])
            ->assertFailed();

        $this->assertSame($oldJson, Storage::disk('private')->get('maddrax.json'));
        $this->assertDatabaseHas('books', ['roman_number' => 1, 'title' => 'Alt', 'cycle' => 'Euree']);
    }

    private function articleHtml(int $number, string $cycle): string
    {
        return '<html><body><b>'.$number.'</b><table>
            <tr><td>Erstmals&nbsp;erschienen:</td><td>2026-09-01</td></tr>
            <tr><td>Zyklus:</td><td>'.$cycle.' (1)</td></tr>
            <tr><td>Titel:</td><th>Roman '.$number.'</th></tr>
            <tr><td>Text:</td><td>Autor</td></tr>
        </table></body></html>';
    }

    /** @return array<string, mixed> */
    private function row(int $number, string $cycle, string $title): array
    {
        return [
            'nummer' => $number,
            'evt' => '2026-09-01',
            'zyklus' => $cycle,
            'titel' => $title,
            'text' => ['Autor'],
            'bewertung' => null,
            'stimmen' => 0,
            'personen' => null,
            'schlagworte' => null,
            'orte' => null,
            'maddraxikon_seitentitel' => 'MX '.$number,
        ];
    }

    /** @param array<string, array<int, array<string, mixed>>> $datasets */
    private function activateSnapshot(string $id, array $datasets): void
    {
        $json = collect($datasets)
            ->map(static fn (array $rows): string => (string) json_encode($rows, JSON_THROW_ON_ERROR))
            ->all();

        DB::transaction(
            fn () => app(MaddraxikonSnapshotRepository::class)->storeAndActivate($id, $json),
        );
    }
}
