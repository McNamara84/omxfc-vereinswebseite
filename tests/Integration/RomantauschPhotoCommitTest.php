<?php

namespace Tests\Integration;

use App\Enums\BookType;
use App\Enums\Role;
use App\Livewire\RomantauschBundleForm;
use App\Livewire\RomantauschIndex;
use App\Models\Book;
use App\Models\BookOffer;
use App\Models\User;
use App\Services\Romantausch\BookPhotoService;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

/** Real root transactions with the production transaction manager, without RefreshDatabase. */
class RomantauschPhotoCommitTest extends TestCase
{
    use CreatesUserWithRole;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate:fresh', ['--seed' => true])->assertExitCode(0);
        $this->assertSame(DatabaseTransactionsManager::class, app('db.transactions')::class);
        $this->assertSame(0, DB::transactionLevel());
        Storage::fake('public');
        Mail::fake();
        foreach ([1, 2] as $number) {
            Book::create(['roman_number' => $number, 'title' => "Roman{$number}", 'author' => 'Autor', 'type' => BookType::MaddraxDieDunkleZukunftDerErde]);
        }
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
        parent::tearDown();
    }

    private function data(User $owner, array $extra = []): array
    {
        return $extra + ['user_id' => $owner->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'Z0'];
    }

    public static function transactionOutcomes(): array
    {
        return ['commit' => [true], 'rollback' => [false]];
    }

    private function finishTransaction(bool $commit): void
    {
        $this->assertSame(1, DB::transactionLevel());
        $this->assertTrue(DB::connection()->getPdo()->inTransaction());
        $commit ? DB::commit() : DB::rollBack();
        $this->assertSame(0, DB::transactionLevel());
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
    }

    #[DataProvider('transactionOutcomes')]
    public function test_bundle_photo_replacement_waits_for_the_root_commit(bool $commit): void
    {
        $owner = $this->actingMember();
        $paths = ['book-offers/shared.jpg', 'book-offers/first.jpg', 'book-offers/second.jpg', 'book-offers/third.jpg'];
        foreach ($paths as $path) {
            Storage::disk('public')->put($path, 'photo');
        }
        $firstPhotos = array_slice($paths, 0, 2);
        $secondPhotos = [$paths[0], $paths[2], $paths[3]];
        $first = BookOffer::create($this->data($owner, ['bundle_id' => 'commit-bundle', 'photos' => $firstPhotos]));
        $second = BookOffer::create($this->data($owner, ['bundle_id' => 'commit-bundle', 'book_number' => 2, 'photos' => $secondPhotos]));

        DB::beginTransaction();
        Livewire::test(RomantauschBundleForm::class, ['bundleId' => 'commit-bundle'])
            ->set('condition', 'Z1')->set('remove_photos', [$paths[2], $paths[3]])
            ->set('photos', [UploadedFile::fake()->image('replacement.jpg')])
            ->call('save')->assertHasNoErrors()->assertRedirect(route('romantausch.index'));

        $savedPhotos = $first->fresh()->photos;
        $this->assertCount(3, $savedPhotos);
        $uploaded = array_values(array_diff($savedPhotos, $paths));
        $this->assertCount(1, $uploaded);
        Storage::disk('public')->assertExists([...$paths, ...$uploaded]);
        $this->finishTransaction($commit);

        if ($commit) {
            Storage::disk('public')->assertMissing([$paths[2], $paths[3]]);
            Storage::disk('public')->assertExists($savedPhotos);
            $this->assertSame($savedPhotos, $first->fresh()->photos);
            $this->assertSame($savedPhotos, $second->fresh()->photos);
        } else {
            Storage::disk('public')->assertMissing($uploaded);
            $this->assertSame($firstPhotos, $first->fresh()->photos);
            $this->assertSame($secondPhotos, $second->fresh()->photos);
            $this->assertSame('Z0', $first->fresh()->condition);
            DB::transaction(static fn () => null);
            Storage::disk('public')->assertExists($paths);
        }
    }

    #[DataProvider('transactionOutcomes')]
    public function test_reference_queries_and_photo_deletion_wait_for_the_root_commit(bool $commit): void
    {
        $this->actingMember();
        $removed = 'book-offers/remove "quoted".jpg';
        $shared = 'book-offers/shared "quoted".jpg';
        $unreferenced = 'book-offers/unreferenced.jpg';
        $paths = [$removed, $shared, $unreferenced];
        foreach ($paths as $path) {
            Storage::disk('public')->put($path, 'photo');
        }
        BookOffer::create($this->data($this->createUserWithRole(Role::Mitglied), ['completed' => true, 'photos' => [$shared, $removed.'.backup']]));
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, 'book_offers')) {
                $queries[] = $query->sql;
            }
        });

        DB::beginTransaction();
        DB::transaction(fn () => app(BookPhotoService::class)->removeUnreferencedAfterCommit([...$paths, $removed]));
        $this->assertSame([], $queries);
        Storage::disk('public')->assertExists($paths);
        $this->finishTransaction($commit);

        if ($commit) {
            Storage::disk('public')->assertMissing([$removed, $unreferenced]);
            Storage::disk('public')->assertExists($shared);
            $this->assertCount(3, $queries);
            foreach ($queries as $sql) {
                $this->assertStringContainsString('select exists', strtolower($sql));
            }
        } else {
            DB::transaction(static fn () => null);
            $this->assertSame([], $queries);
            Storage::disk('public')->assertExists($paths);
        }
    }

    #[DataProvider('transactionOutcomes')]
    public function test_shared_photo_survives_until_the_last_reference_deletion_is_committed(bool $commit): void
    {
        $owner = $this->actingMember();
        $path = 'book-offers/shared.jpg';
        Storage::disk('public')->put($path, 'photo');
        $first = BookOffer::create($this->data($owner, ['photos' => [$path]]));
        $last = BookOffer::create($this->data($owner, ['book_number' => 2, 'photos' => [$path]]));

        $this->delete(route('romantausch.delete-offer', $first))->assertRedirect();
        $this->assertSame(0, DB::transactionLevel());
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseMissing('book_offers', ['id' => $first->id]);

        DB::beginTransaction();
        Livewire::test(RomantauschIndex::class)->call('deleteOffer', $last->id)->assertSuccessful();
        Storage::disk('public')->assertExists($path);
        $this->finishTransaction($commit);

        if ($commit) {
            Storage::disk('public')->assertMissing($path);
            $this->assertDatabaseMissing('book_offers', ['id' => $last->id]);
        } else {
            $this->assertDatabaseHas('book_offers', ['id' => $last->id]);
            DB::transaction(static fn () => null);
            Storage::disk('public')->assertExists($path);
        }
    }
}
