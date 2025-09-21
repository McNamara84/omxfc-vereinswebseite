<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BrowserStatsService
{
    /**
     * Liefert Statistiken zur Browsernutzung der Mitglieder basierend auf den aktuellsten Sitzungen.
     *
     * @return array{browserCounts: Collection<int, array{label: string, value: int}>, familyCounts: Collection<int, array{label: string, value: int}>}
     */
    public function browserUsage(): array
    {
        $latestSessions = $this->latestSessions();

        $detected = $latestSessions
            ->filter(fn ($session) => filled($session->user_agent))
            ->map(fn ($session) => $this->detectBrowser($session->user_agent));

        $browserCounts = $detected
            ->map(fn ($info) => $info['browser'])
            ->countBy()
            ->sortDesc()
            ->map(fn ($count, $label) => ['label' => $label, 'value' => (int) $count])
            ->values();

        $familyCounts = $detected
            ->map(fn ($info) => $info['family'])
            ->countBy()
            ->sortDesc()
            ->map(fn ($count, $label) => ['label' => $label, 'value' => (int) $count])
            ->values();

        return [
            'browserCounts' => $browserCounts,
            'familyCounts' => $familyCounts,
        ];
    }

    /**
     * Ermittelt den Browsernamen und die Browserfamilie für einen User-Agent.
     */
    public function detectBrowser(?string $userAgent): array
    {
        if (! is_string($userAgent) || trim($userAgent) === '') {
            return [
                'browser' => 'Unbekannt',
                'family' => 'Sonstige',
            ];
        }

        foreach ($this->browserDefinitions() as $definition) {
            if ($this->matchesDefinition($userAgent, $definition)) {
                return [
                    'browser' => $definition['name'],
                    'family' => $definition['family'],
                ];
            }
        }

        return [
            'browser' => 'Andere',
            'family' => 'Sonstige',
        ];
    }

    /**
     * Gibt die aktuellste Sitzung pro Mitglied zurück.
     */
    private function latestSessions(): Collection
    {
        return DB::table('sessions')
            ->select('user_id', 'user_agent', 'last_activity')
            ->whereNotNull('user_id')
            ->whereNotNull('user_agent')
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->sortByDesc('last_activity')->first());
    }

    /**
     * Definition der Browser inkl. Erkennungslogik.
     *
     * @return array<int, array{name: string, family: string, match: array<int, string>, exclude?: array<int, string>}>
     */
    private function browserDefinitions(): array
    {
        return [
            ['name' => 'Microsoft Edge', 'family' => 'Chromium', 'match' => ['Edg/']],
            ['name' => 'Opera', 'family' => 'Chromium', 'match' => ['OPR/', 'Opera']],
            ['name' => 'Vivaldi', 'family' => 'Chromium', 'match' => ['Vivaldi']],
            ['name' => 'Brave', 'family' => 'Chromium', 'match' => ['Brave/']],
            ['name' => 'Samsung Internet', 'family' => 'Chromium', 'match' => ['SamsungBrowser']],
            ['name' => 'Tor Browser', 'family' => 'Firefox', 'match' => ['TorBrowser']],
            [
                'name' => 'Google Chrome',
                'family' => 'Chromium',
                'match' => ['Chrome', 'CriOS'],
                'exclude' => ['Edg', 'OPR', 'SamsungBrowser', 'Brave', 'Vivaldi'],
            ],
            [
                'name' => 'Mozilla Firefox',
                'family' => 'Firefox',
                'match' => ['Firefox', 'FxiOS'],
            ],
            [
                'name' => 'Safari',
                'family' => 'WebKit',
                'match' => ['Safari'],
                'exclude' => ['Chrome', 'Chromium', 'OPR', 'FxiOS', 'CriOS', 'Edg', 'SamsungBrowser'],
            ],
            [
                'name' => 'Internet Explorer',
                'family' => 'Legacy (IE)',
                'match' => ['MSIE', 'Trident'],
            ],
        ];
    }

    /**
     * Prüft, ob der User-Agent zu einer Definition passt.
     *
     * @param array{name: string, family: string, match: array<int, string>, exclude?: array<int, string>} $definition
     */
    private function matchesDefinition(string $userAgent, array $definition): bool
    {
        $haystack = Str::lower($userAgent);
        $matches = collect($definition['match'])
            ->map(fn ($needle) => Str::lower($needle))
            ->some(fn ($needle) => str_contains($haystack, $needle));

        if (! $matches) {
            return false;
        }

        $excludes = collect($definition['exclude'] ?? [])
            ->map(fn ($needle) => Str::lower($needle));

        return ! $excludes->some(fn ($needle) => str_contains($haystack, $needle));
    }
}
