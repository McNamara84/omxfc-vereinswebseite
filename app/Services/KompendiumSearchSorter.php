<?php

namespace App\Services;

use App\Models\KompendiumRoman;

class KompendiumSearchSorter
{
    public const SORT_RELEVANCE = 'relevance';

    public const SORT_FIRST_PUBLISHED = 'first_published';

    public const DIRECTION_ASC = 'asc';

    public const DIRECTION_DESC = 'desc';

    private const VERY_OLD_DATE = '0000-01-01';

    private const METADATA_PATH_CHUNK_SIZE = 500;

    /**
     * @return list<string>
     */
    public function validSorts(): array
    {
        return [self::SORT_RELEVANCE, self::SORT_FIRST_PUBLISHED];
    }

    /**
     * @return list<string>
     */
    public function validDirections(): array
    {
        return [self::DIRECTION_ASC, self::DIRECTION_DESC];
    }

    public function normalizeSort(mixed $sort): string
    {
        return in_array($sort, $this->validSorts(), true)
            ? $sort
            : self::SORT_RELEVANCE;
    }

    public function normalizeDirection(mixed $direction, string $sort): string
    {
        return in_array($direction, $this->validDirections(), true)
            ? $direction
            : $this->defaultDirectionForSort($sort);
    }

    public function defaultDirectionForSort(string $sort): string
    {
        return $sort === self::SORT_FIRST_PUBLISHED
            ? self::DIRECTION_ASC
            : self::DIRECTION_DESC;
    }

    public function needsFullPostFilter(string $sort, string $direction): bool
    {
        return $sort === self::SORT_FIRST_PUBLISHED
            || ($sort === self::SORT_RELEVANCE && $direction === self::DIRECTION_ASC);
    }

    /**
     * @param  list<string>  $paths
     * @return array{paths: list<string>, metadata: array<string, array{erstveroeffentlichtAm: string|null, erstveroeffentlichtAmFormatted: string|null, serie: string, romanNrSort: int}>}
     */
    public function orderPathsWithMetadata(array $paths, string $sort, string $direction): array
    {
        $metadata = $this->metadataForPaths($paths);

        if ($sort === self::SORT_RELEVANCE) {
            return [
                'paths' => $direction === self::DIRECTION_ASC ? array_reverse($paths) : $paths,
                'metadata' => $metadata,
            ];
        }

        $indexedPaths = [];

        foreach ($paths as $position => $path) {
            $indexedPaths[] = [
                'path' => $path,
                'position' => $position,
            ];
        }

        usort($indexedPaths, function (array $a, array $b) use ($metadata, $direction): int {
            $pathA = $a['path'];
            $pathB = $b['path'];
            $metaA = $metadata[$pathA] ?? $this->fallbackMetadata($pathA);
            $metaB = $metadata[$pathB] ?? $this->fallbackMetadata($pathB);

            $dateA = $metaA['erstveroeffentlichtAm'] ?? self::VERY_OLD_DATE;
            $dateB = $metaB['erstveroeffentlichtAm'] ?? self::VERY_OLD_DATE;
            $dateComparison = strcmp($dateA, $dateB);

            if ($dateComparison !== 0) {
                return $direction === self::DIRECTION_ASC ? $dateComparison : -$dateComparison;
            }

            $serieComparison = strcmp($metaA['serie'], $metaB['serie']);

            if ($serieComparison !== 0) {
                return $serieComparison;
            }

            $romanComparison = $metaA['romanNrSort'] <=> $metaB['romanNrSort'];

            if ($romanComparison !== 0) {
                return $romanComparison;
            }

            $pathComparison = strcmp($pathA, $pathB);

            if ($pathComparison !== 0) {
                return $pathComparison;
            }

            return $a['position'] <=> $b['position'];
        });

        return [
            'paths' => array_values(array_map(fn (array $entry): string => $entry['path'], $indexedPaths)),
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, array{erstveroeffentlichtAm: string|null, erstveroeffentlichtAmFormatted: string|null, serie: string, romanNrSort: int}>
     */
    private function metadataForPaths(array $paths): array
    {
        $uniquePaths = array_values(array_unique($paths));

        if ($uniquePaths === []) {
            return [];
        }

        $romane = [];

        foreach (array_chunk($uniquePaths, self::METADATA_PATH_CHUNK_SIZE) as $pathChunk) {
            foreach (
                KompendiumRoman::query()
                    ->select(['dateipfad', 'serie', 'roman_nr', 'erstveroeffentlicht_am'])
                    ->whereIn('dateipfad', $pathChunk)
                    ->get() as $roman
            ) {
                $romane[$roman->dateipfad] = $roman;
            }
        }

        $metadata = [];

        foreach ($uniquePaths as $path) {
            /** @var KompendiumRoman|null $roman */
            $roman = $romane[$path] ?? null;

            $metadata[$path] = [
                'erstveroeffentlichtAm' => $roman?->erstveroeffentlicht_am?->toDateString(),
                'erstveroeffentlichtAmFormatted' => $roman?->erstveroeffentlicht_am?->format('d.m.Y'),
                'serie' => $roman?->serie ?? $this->extractSerie($path),
                'romanNrSort' => $roman?->roman_nr ?? $this->extractRomanNr($path),
            ];
        }

        return $metadata;
    }

    /**
     * @return array{erstveroeffentlichtAm: string|null, erstveroeffentlichtAmFormatted: string|null, serie: string, romanNrSort: int}
     */
    private function fallbackMetadata(string $path): array
    {
        return [
            'erstveroeffentlichtAm' => null,
            'erstveroeffentlichtAmFormatted' => null,
            'serie' => $this->extractSerie($path),
            'romanNrSort' => $this->extractRomanNr($path),
        ];
    }

    private function extractSerie(string $path): string
    {
        $parts = preg_split('#[\\\\\/]+#', $path);

        return $parts[1] ?? 'unknown';
    }

    private function extractRomanNr(string $path): int
    {
        $filename = pathinfo($path, PATHINFO_FILENAME);

        if (preg_match('/^(\d+)/', $filename, $matches) !== 1) {
            return PHP_INT_MAX;
        }

        return (int) $matches[1];
    }
}
