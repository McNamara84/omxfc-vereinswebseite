<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Services\BildersucheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesBildersucheCover;
use Tests\TestCase;

class BildersucheSearchTest extends TestCase
{
    use CreatesBildersucheCover;
    use RefreshDatabase;

    #[DataProvider('queries')]
    public function test_search_matches_all_terms_in_cover_keywords(string $query, array $numbers): void
    {
        $this->exampleCovers();
        $results = app(BildersucheService::class)->search($query);
        $this->assertSame($numbers, $results->getCollection()->map(fn ($cover) => $cover->book->roman_number)->all());
    }

    public static function queries(): array
    {
        return [['Aruula', [1]], ['Drax', [1, 2]], ['mAtThEw DRAX', [1, 2]], ['Aruula Schwert', [1]],
            ['Drax Wulfane', [2]], ['Aruula Wulfane', []], ['F-17 Alpha 2', [1]], ["  Drax\n Drax ", [1, 2]], ['Drax NOT', []]];
    }

    public function test_special_characters_and_accents_are_literal(): void
    {
        $this->searchCover(1, ['100% echt', 'A_B', 'A!B', 'Äther']);
        $this->searchCover(2, ['1000 echt', 'AxB', 'Aether']);
        foreach (['100%', 'a_b', 'a!b', 'äther'] as $query) {
            $this->assertSame([1], app(BildersucheService::class)->search($query)->getCollection()->map(fn ($cover) => $cover->book->roman_number)->all());
        }
        $this->assertSame(0, app(BildersucheService::class)->search('ather')->total());
    }

    public function test_excludes_untagged_unready_unmapped_and_title_only_matches(): void
    {
        $this->searchCover(1, [])->book->update(['title' => 'Schwert']);
        $this->searchCover(2, ['Schwert'])->update(['status' => 'pending']);
        $this->searchCover(3, ['Schwert'])->update(['source_file_title' => 'Datei:Neu.jpg']);
        $this->searchCover(4, ['Schwert'])->update(['small_path' => '']);
        $this->searchCover(5, ['Schwert'])->update(['large_path' => null]);
        $this->searchCover(6, ['Schwert'])->update(['keywords_synced_at' => null]);
        $this->searchCover(7, ['Schwert'])->update(['keywords_sync_error' => 'Timeout']);
        $this->assertSame([7], app(BildersucheService::class)->search('Schwert')->getCollection()->map(fn ($cover) => $cover->book->roman_number)->all());
    }

    public function test_filters_all_supported_series_and_paginates_without_duplicates(): void
    {
        foreach (range(1, 26) as $number) {
            $this->searchCover($number, ['Drax', 'Matthew Drax']);
        }
        foreach (array_slice(BookType::cases(), 1) as $type) {
            $this->searchCover(1, ['Drax'], $type);
            $this->assertSame(1, app(BildersucheService::class)->search('Drax', $type->key())->total());
        }
        $first = app(BildersucheService::class)->search('Drax', 'maddrax', 1);
        $second = app(BildersucheService::class)->search('Drax', 'maddrax', 2);
        $this->assertSame(26, $first->total());
        $this->assertCount(24, $first);
        $this->assertSame([25, 26], $second->getCollection()->map(fn ($cover) => $cover->book->roman_number)->all());
        $this->assertSame(31, app(BildersucheService::class)->search('Drax')->total());
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_requests_are_rejected(string $query, string $series, int $page): void
    {
        $this->expectException(ValidationException::class);
        app(BildersucheService::class)->search($query, $series, $page);
    }

    public static function invalidQueries(): array
    {
        return [['', 'all', 1], ['a', 'all', 1], [str_repeat('x', 201), 'all', 1], ['a b c d e f g h i j k', 'all', 1], ['Drax', 'bad', 1], ['Drax', 'all', 0], ['Drax', 'all', 100001]];
    }
}
