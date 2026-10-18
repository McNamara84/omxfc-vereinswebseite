<?php

namespace App\Services;

use App\Enums\BookCoverStatus;
use App\Enums\BookType;
use App\Models\BookCover;
use App\Support\CoverRatings\CoverKeywords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class BildersucheService
{
    public const int PER_PAGE = 24;

    /** @return list<string> */
    public function terms(string $query): array
    {
        $query = CoverKeywords::normalize($query);
        $terms = array_values(array_unique(explode(' ', $query)));
        if (mb_strlen($query) < 2 || mb_strlen($query) > 200 || count($terms) > 10) {
            throw ValidationException::withMessages(['query' => 'Bitte gib 2 bis 200 Zeichen und höchstens 10 Suchbegriffe ein.']);
        }

        return $terms;
    }

    public function searchable(): Builder
    {
        $query = BookCover::query()
            ->where('status', BookCoverStatus::Ready)
            ->whereNotNull('small_path')->where('small_path', '!=', '')
            ->whereNotNull('large_path')->where('large_path', '!=', '')
            ->whereNotNull('keywords_synced_at')
            ->whereHas('keywords');

        return in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
            ? $query->whereRaw('BINARY keywords_source_file_title = BINARY source_file_title')
            : $query->whereColumn('keywords_source_file_title', 'source_file_title');
    }

    public function search(string $query, string $series = 'all', int $page = 1): LengthAwarePaginator
    {
        $terms = $this->terms($query);
        $type = BookType::fromKey($series);
        if ($series !== 'all' && ! $type) {
            throw ValidationException::withMessages(['series' => 'Bitte wähle eine gültige Serie.']);
        }
        if ($page < 1 || $page > 100_000) {
            throw ValidationException::withMessages(['page' => 'Ungültige Ergebnisseite.']);
        }

        $covers = $this->searchable()->with(['book', 'keywords']);
        foreach ($terms as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
            $covers->whereHas('keywords', fn (Builder $keywords) => $keywords
                ->whereRaw("normalized_keyword LIKE ? ESCAPE '!'", [$pattern]));
        }
        $covers->join('books', 'books.id', '=', 'book_covers.book_id')->select('book_covers.*');
        if ($type) {
            $covers->where('books.type', $type);
        }
        $cases = BookType::cases();
        $order = 'CASE books.type '.implode(' ', array_map(
            fn (int $index): string => 'WHEN ? THEN '.$index, array_keys($cases),
        )).' ELSE 99 END';

        return $covers->orderByRaw($order, array_map(fn (BookType $case): string => $case->value, $cases))
            ->orderBy('books.roman_number')->orderBy('book_covers.id')
            ->paginate(self::PER_PAGE, ['book_covers.*'], 'page', $page);
    }
}
