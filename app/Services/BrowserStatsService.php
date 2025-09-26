<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BrowserStatsService
{
    /**
     * Liefert Statistiken zur Browser- und Gerätenutzung der Mitglieder basierend auf allen Sitzungen der letzten 30 Tage.
     *
     * @return array{
     *     browserCounts: Collection<int, array{label: string, value: int}>,
     *     familyCounts: Collection<int, array{label: string, value: int}>,
     *     deviceTypeCounts: Collection<int, array{label: string, value: int}>,
     * }
     */
    public function browserUsage(): array
    {
        $recentSessions = $this->recentSessions();

        $detected = $recentSessions
            ->filter(fn ($session) => filled($session->user_agent))
            ->map(fn ($session) => $this->detectBrowser($session->user_agent));

        $deviceTypes = $recentSessions
            ->map(fn ($session) => $this->detectDeviceType($session->user_agent ?? null));

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

        $deviceTypeCounts = $deviceTypes
            ->countBy()
            ->sortDesc()
            ->map(fn ($count, $label) => ['label' => $label, 'value' => (int) $count])
            ->values();

        return [
            'browserCounts' => $browserCounts,
            'familyCounts' => $familyCounts,
            'deviceTypeCounts' => $deviceTypeCounts,
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

    public function detectDeviceType(?string $userAgent): string
    {
        if (! is_string($userAgent) || trim($userAgent) === '') {
            return 'Festgerät';
        }

        $haystack = Str::lower($userAgent);

        $mobileKeywords = [
            'mobile',
            'iphone',
            'ipad',
            'ipod',
            'android',
            'opera mini',
            'opera mobi',
            'blackberry',
            'windows phone',
            'iemobile',
            'silk',
            'kindle',
            'tablet',
        ];

        if (collect($mobileKeywords)->some(fn ($keyword) => str_contains($haystack, $keyword))) {
            return 'Mobilgerät';
        }

        return 'Festgerät';
    }

    /**
     * Gibt alle eindeutigen Browser-Sitzungen der letzten 30 Tage pro Mitglied zurück.
     */
    private function recentSessions(): Collection
    {
        $threshold = now()->subDays(30);

        return DB::table('member_client_snapshots')
            ->select('user_id', 'user_agent', 'last_seen_at')
            ->where('last_seen_at', '>=', $threshold)
            ->get()
            ->sortByDesc('last_seen_at')
            ->unique(fn ($session) => [$session->user_id, $session->user_agent])
            ->values();
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
