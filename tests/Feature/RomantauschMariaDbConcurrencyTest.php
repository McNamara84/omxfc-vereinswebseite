<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\BaxxEarningRule;
use App\Models\Book;
use App\Models\BookOffer;
use App\Models\BookSwap;
use App\Models\UserPoint;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RomantauschMariaDbConcurrencyTestFixtures::class);
dataset('RomantauschMariaDbConcurrencyTest.sourceRaces', static fn (): array => [['update'], ['delete']]);
beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Benötigt echte MariaDB-Verbindungen.');
    }
    if (DB::connection()->getDatabaseName() !== 'omxfc_maddraxikon_test') {
        throw new RuntimeException('Unsichere Testdatenbank verweigert.');
    }
    foreach (range(1, 3) as $number) {
        Book::create(['roman_number' => $number, 'title' => "Roman {$number}", 'author' => 'Autor', 'type' => BookType::MaddraxDieDunkleZukunftDerErde]);
    }
    BaxxEarningRule::whereIn('action_key', ['romantausch_offer', 'romantausch_swap_complete'])->update(['points' => 3, 'every_count' => 1, 'is_active' => true]);
});
afterEach(function (): void {
    try {
        if ($this->committed) {
            $this->artisan('migrate:fresh')->assertExitCode(0);
        }
    } finally {
    }
});
test('parallel creations cross a threshold only once', function (): void {
    [$offer] = $this->sources();
    BaxxEarningRule::where('action_key', 'romantausch_offer')->update(['every_count' => 2]);
    $this->race([['create', $offer->user_id], ['create', $offer->user_id]]);
    expect(BookOffer::where('user_id', $offer->user_id)->count())->toBe(3)
        ->and(UserPoint::count())->toBe(1)
        ->and((int) UserPoint::sum('points'))->toBe(3);
});
test('parallel matchers create one reciprocal pair and two notifications', function (): void {
    [$offer] = $this->sources(true);
    $results = $this->race([['match', $offer->user_id, $offer->id], ['match', $offer->user_id, $offer->id]]);
    $this->assertDatabaseCount('book_swaps', 2);
    expect(array_sum(array_column($results, 'mails')))->toBe(2)
        ->and(BookSwap::distinct()->count('offer_id'))->toBe(2)
        ->and(BookSwap::distinct()->count('request_id'))->toBe(2);
});
test('parallel confirmations award each participant once', function (): void {
    [$offer, $request] = $this->sources();
    $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    $this->race([['confirm', $offer->user_id, $swap->id], ['confirm', $request->user_id, $swap->id]]);
    expect($swap->fresh()->completed_at)->not->toBeNull()
        ->and(UserPoint::count())->toBe(2)
        ->and((int) UserPoint::sum('points'))->toBe(6)
        ->and(Activity::where('action', 'swap_completed')->count())->toBe(1);
});
test('confirmation against manual completion is idempotent', function (): void {
    $manager = $this->createUserWithRole(Role::Vorstand);
    [$offer, $request] = $this->sources();
    $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id, 'offer_confirmed' => true]);
    $this->race([['confirm', $request->user_id, $swap->id], ['complete', $manager->id, $offer->id, $request->id]]);
    $this->assertDatabaseCount('book_swaps', 1);
    expect(UserPoint::count())->toBe(2)
        ->and(Activity::where('action', 'swap_completed')->count())->toBe(1);
});
test('matching against source mutation remains consistent', function (string $mutation): void {
    [$offer, $request] = $this->sources(true);
    $this->race([['match', $offer->user_id, $offer->id], [$mutation, $offer->user_id, $offer->id]]);
    expect(BookSwap::whereDoesntHave('offer')->orWhereDoesntHave('request')->count())->toBe(0)
        ->and(BookSwap::distinct()->count('offer_id'))->toBe(BookSwap::count());
    if ($mutation === 'delete') {
        expect($offer->fresh())->toBeNull()
            ->and(BookSwap::where('offer_id', $offer->id)->exists())->toBeFalse();
    }
})->with('RomantauschMariaDbConcurrencyTest.sourceRaces');
test('bundle change against matching rechecks current state', function (): void {
    [$offer] = $this->sources(true);
    $offer->update(['bundle_id' => 'parallel-bundle']);
    BookOffer::create(['user_id' => $offer->user_id, 'bundle_id' => 'parallel-bundle', 'series' => $offer->series, 'book_number' => 3, 'book_title' => 'Roman 3', 'condition' => 'Z0']);
    $this->race([['match', $offer->user_id, $offer->id], ['bundle-update', $offer->user_id, 'parallel-bundle']]);
    expect(BookSwap::distinct()->count('offer_id'))->toBe(BookSwap::count())
        ->and(BookSwap::whereDoesntHave('offer')->orWhereDoesntHave('request')->count())->toBe(0);
});
test('bundle change against deletion never recreates deleted sources', function (): void {
    [$offer] = $this->sources();
    $offer->update(['bundle_id' => 'parallel-bundle']);
    BookOffer::create(['user_id' => $offer->user_id, 'bundle_id' => 'parallel-bundle', 'series' => $offer->series, 'book_number' => 2, 'book_title' => 'Roman 2', 'condition' => 'Z0']);
    $this->race([['bundle-update', $offer->user_id, 'parallel-bundle'], ['bundle-delete', $offer->user_id, 'parallel-bundle']]);
    expect(BookOffer::where('bundle_id', 'parallel-bundle')->count())->toBe(0);
});
