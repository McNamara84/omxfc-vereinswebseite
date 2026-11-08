<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Actions\Romantausch\OfferWorkflow;
use App\Enums\BookType;
use App\Models\BaxxEarningRule;
use App\Models\Book;
use App\Models\BookOffer;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class);

beforeEach(function (): void {
    // A disposable SQLite database gives this test a real root transaction.
    $this->artisan('migrate:fresh', ['--seed' => true])->assertExitCode(0);
});

afterEach(function (): void {
    RefreshDatabaseState::$migrated = false;
    RefreshDatabaseState::$inMemoryConnections = [];
});

test('retryable database failure does not repeat the upload', function (): void {
    Storage::fake('public');
    $actor = $this->actingMember();
    Book::create(['roman_number' => 1, 'title' => 'Roman 1', 'author' => 'Autor', 'type' => BookType::MaddraxDieDunkleZukunftDerErde]);
    BaxxEarningRule::where('action_key', 'romantausch_offer')->update(['points' => 5, 'every_count' => 1, 'is_active' => true]);
    $attempts = 0;
    BookOffer::creating(function () use (&$attempts): void {
        if ($attempts++ === 0) {
            throw new \PDOException('Deadlock found when trying to get lock; try restarting transaction');
        }
    });
    $result = app(OfferWorkflow::class)->create($actor, ['series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'condition' => 'Z0', 'photos' => [UploadedFile::fake()->image('cover.jpg')]]);
    expect($attempts)->toBe(2);
    $this->assertDatabaseCount('book_offers', 1);
    $this->assertDatabaseCount('user_points', 1);
    expect(Storage::disk('public')->allFiles('book-offers'))->toBe($result->model->photos);
});
