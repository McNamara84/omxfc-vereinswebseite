<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Enums\Role;
use App\Models\BaxxEarningProgress;
use App\Models\BaxxEarningRule;
use App\Models\Book;
use App\Models\BookCover;
use App\Models\CoverRating;
use App\Models\UserPoint;
use App\Services\CoverRatings\CoverRatingBaxxService;
use App\Services\CoverRatings\CoverRatingService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CoverRatingMariaDbConcurrencyTestFixtures::class);

covers(CoverRatingService::class);

covers(CoverRatingBaxxService::class);

afterEach(function (): void {
    try {
        if ($this->committedTestDatabase && isset($this->app)) {
            $exitCode = $this->artisan('migrate:fresh')->execute();
            if ($exitCode !== 0) {
                throw new RuntimeException("Die MariaDB-Testdatenbank konnte nicht bereinigt werden (Exit {$exitCode}).");
            }
        }
    } finally {
    }
});

test('two parallel ratings cross the hundred boundary exactly once', function (): void {
    $this->requireSafeMariaDbTestDatabase();
    $member = $this->createUserWithRole(Role::Mitglied);
    $covers = collect(range(1, 101))->map(function (int $number): BookCover {
        $book = Book::factory()->create(['type' => BookType::MaddraxDieDunkleZukunftDerErde, 'roman_number' => 20000 + $number]);

        return BookCover::factory()->for($book)->create();
    });
    foreach ($covers->take(99) as $cover) {
        CoverRating::factory()->for($member)->for($cover, 'bookCover')->create();
    }
    BaxxEarningRule::query()->updateOrCreate(['action_key' => CoverRatingBaxxService::ACTION_KEY], ['label' => 'Cover-Bewertungen', 'description' => 'MariaDB-Paralleltest', 'points' => 1, 'every_count' => 100, 'is_active' => true]);
    expect(DB::transactionLevel())->toBe(1);
    DB::connection()->commit();
    $this->committedTestDatabase = true;
    $first = $this->startWorker($member->id, $covers[99]->id);
    $second = $this->startWorker($member->id, $covers[100]->id);
    try {
        expect($this->readWorkerResult($first)['event'] ?? null)->toBe('ready')
            ->and($this->readWorkerResult($second)['event'] ?? null)->toBe('ready');
        $this->sendWorkerCommand($first, 'RATE');
        $this->sendWorkerCommand($second, 'RATE');
        $firstResult = $this->readWorkerResult($first);
        $secondResult = $this->readWorkerResult($second);
        $firstExitCode = $this->finishWorker($first);
        $secondExitCode = $this->finishWorker($second);
    } finally {
        $this->terminateWorker($first);
        $this->terminateWorker($second);
    }
    expect($firstExitCode)->toBe(0)
        ->and($secondExitCode)->toBe(0)
        ->and($firstResult['ok'] ?? false)->toBeTrue()
        ->and($secondResult['ok'] ?? false)->toBeTrue();
    $awards = [(int) ($firstResult['awarded_baxx'] ?? -1), (int) ($secondResult['awarded_baxx'] ?? -1)];
    sort($awards);
    expect($awards)->toBe([0, 1])
        ->and(CoverRating::query()->where('user_id', $member->id)->count())->toBe(101)
        ->and((int) UserPoint::query()->where('user_id', $member->id)->sum('points'))->toBe(1)
        ->and((int) BaxxEarningProgress::query()->where('user_id', $member->id)->where('action_key', CoverRatingBaxxService::ACTION_KEY)->value('processed_count'))->toBe(101);
});
