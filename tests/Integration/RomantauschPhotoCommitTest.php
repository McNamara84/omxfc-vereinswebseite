<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\BookType;
use App\Enums\Role;
use App\Livewire\RomantauschBundleForm;
use App\Livewire\RomantauschIndex;
use App\Models\Book;
use App\Models\BookOffer;
use App\Services\Romantausch\BookPhotoService;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RomantauschPhotoCommitTestFixtures::class);
dataset('RomantauschPhotoCommitTest.transactionOutcomes', static fn (): array => ['commit' => [true], 'rollback' => [false]]);
beforeEach(function (): void {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
    DB::purge('sqlite');
    $this->artisan('migrate:fresh', ['--seed' => true])->assertExitCode(0);
    expect(app('db.transactions')::class)->toBe(DatabaseTransactionsManager::class)
        ->and(DB::transactionLevel())->toBe(0);
    Storage::fake('public');
    Mail::fake();
    foreach ([1, 2] as $number) {
        Book::create(['roman_number' => $number, 'title' => "Roman{$number}", 'author' => 'Autor', 'type' => BookType::MaddraxDieDunkleZukunftDerErde]);
    }
});
afterEach(function (): void {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    RefreshDatabaseState::$migrated = false;
    RefreshDatabaseState::$inMemoryConnections = [];
});
test('bundle photo replacement waits for the root commit', function (bool $commit): void {
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
    Livewire::test(RomantauschBundleForm::class, ['bundleId' => 'commit-bundle'])->set('condition', 'Z1')->set('remove_photos', [$paths[2], $paths[3]])->set('photos', [UploadedFile::fake()->image('replacement.jpg')])->call('save')->assertHasNoErrors()->assertRedirect(route('romantausch.index'));
    $savedPhotos = $first->fresh()->photos;
    expect($savedPhotos)->toHaveCount(3);
    $uploaded = array_values(array_diff($savedPhotos, $paths));
    expect($uploaded)->toHaveCount(1);
    Storage::disk('public')->assertExists([...$paths, ...$uploaded]);
    $this->finishTransaction($commit);
    if ($commit) {
        Storage::disk('public')->assertMissing([$paths[2], $paths[3]]);
        Storage::disk('public')->assertExists($savedPhotos);
        expect($first->fresh()->photos)->toBe($savedPhotos)
            ->and($second->fresh()->photos)->toBe($savedPhotos);
    } else {
        Storage::disk('public')->assertMissing($uploaded);
        expect($first->fresh()->photos)->toBe($firstPhotos)
            ->and($second->fresh()->photos)->toBe($secondPhotos)
            ->and($first->fresh()->condition)->toBe('Z0');
        DB::transaction(static fn () => null);
        Storage::disk('public')->assertExists($paths);
    }
})->with('RomantauschPhotoCommitTest.transactionOutcomes');
test('reference queries and photo deletion wait for the root commit', function (bool $commit): void {
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
    expect($queries)->toBe([]);
    Storage::disk('public')->assertExists($paths);
    $this->finishTransaction($commit);
    if ($commit) {
        Storage::disk('public')->assertMissing([$removed, $unreferenced]);
        Storage::disk('public')->assertExists($shared);
        expect($queries)->toHaveCount(3);
        foreach ($queries as $sql) {
            expect(strtolower($sql))->toContain('select exists');
        }
    } else {
        DB::transaction(static fn () => null);
        expect($queries)->toBe([]);
        Storage::disk('public')->assertExists($paths);
    }
})->with('RomantauschPhotoCommitTest.transactionOutcomes');
test('shared photo survives until the last reference deletion is committed', function (bool $commit): void {
    $owner = $this->actingMember();
    $path = 'book-offers/shared.jpg';
    Storage::disk('public')->put($path, 'photo');
    $first = BookOffer::create($this->data($owner, ['photos' => [$path]]));
    $last = BookOffer::create($this->data($owner, ['book_number' => 2, 'photos' => [$path]]));
    $this->delete(route('romantausch.delete-offer', $first))->assertRedirect();
    expect(DB::transactionLevel())->toBe(0);
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
})->with('RomantauschPhotoCommitTest.transactionOutcomes');
