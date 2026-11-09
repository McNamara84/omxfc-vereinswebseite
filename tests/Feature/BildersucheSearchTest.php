<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Services\BildersucheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesBildersucheCover;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesBildersucheCover::class, RefreshDatabase::class);
dataset('BildersucheSearchTest.queries', static fn (): array => [['Aruula', [1]], ['Drax', [1, 2]], ['mAtThEw DRAX', [1, 2]], ['Aruula Schwert', [1]], ['Drax Wulfane', [2]], ['Aruula Wulfane', []], ['F-17 Alpha 2', [1]], ["  Drax\n Drax ", [1, 2]], ['Drax NOT', []]]);
dataset('BildersucheSearchTest.invalidQueries', static fn (): array => [['', 'all', 1], ['a', 'all', 1], [str_repeat('x', 201), 'all', 1], ['a b c d e f g h i j k', 'all', 1], ['Drax', 'bad', 1], ['Drax', 'all', 0], ['Drax', 'all', 100001]]);
test('search matches all terms in cover keywords', function (string $query, array $numbers): void {
    $this->exampleCovers();
    $results = app(BildersucheService::class)->search($query);
    expect($results->getCollection()->map(fn ($cover) => $cover->book->roman_number)->all())->toBe($numbers);
})->with('BildersucheSearchTest.queries');
test('special characters and accents are literal', function (): void {
    $this->searchCover(1, ['100% echt', 'A_B', 'A!B', 'Äther']);
    $this->searchCover(2, ['1000 echt', 'AxB', 'Aether']);
    foreach (['100%', 'a_b', 'a!b', 'äther'] as $query) {
        expect(app(BildersucheService::class)->search($query)->getCollection()->map(fn ($cover) => $cover->book->roman_number)->all())->toBe([1]);
    }
    expect(app(BildersucheService::class)->search('ather')->total())->toBe(0);
});
test('excludes untagged unready unmapped and title only matches', function (): void {
    $this->searchCover(1, [])->book->update(['title' => 'Schwert']);
    $this->searchCover(2, ['Schwert'])->update(['status' => 'pending']);
    $this->searchCover(3, ['Schwert'])->update(['source_file_title' => 'Datei:Neu.jpg']);
    $this->searchCover(4, ['Schwert'])->update(['small_path' => '']);
    $this->searchCover(5, ['Schwert'])->update(['large_path' => null]);
    $this->searchCover(6, ['Schwert'])->update(['keywords_synced_at' => null]);
    $this->searchCover(7, ['Schwert'])->update(['keywords_sync_error' => 'Timeout']);
    expect(app(BildersucheService::class)->search('Schwert')->getCollection()->map(fn ($cover) => $cover->book->roman_number)->all())->toBe([7]);
});
test('filters all supported series and paginates without duplicates', function (): void {
    foreach (range(1, 26) as $number) {
        $this->searchCover($number, ['Drax', 'Matthew Drax']);
    }
    foreach (array_slice(BookType::cases(), 1) as $type) {
        $this->searchCover(1, ['Drax'], $type);
        expect(app(BildersucheService::class)->search('Drax', $type->key())->total())->toBe(1);
    }
    $first = app(BildersucheService::class)->search('Drax', 'maddrax', 1);
    $second = app(BildersucheService::class)->search('Drax', 'maddrax', 2);
    expect($first->total())->toBe(26)
        ->and($first)->toHaveCount(24)
        ->and($second->getCollection()->map(fn ($cover) => $cover->book->roman_number)->all())->toBe([25, 26])
        ->and(app(BildersucheService::class)->search('Drax')->total())->toBe(31);
});
test('invalid requests are rejected', function (string $query, string $series, int $page): void {
    expect(fn () => app(BildersucheService::class)->search($query, $series, $page))->toThrow(ValidationException::class);
})->with('BildersucheSearchTest.invalidQueries');
