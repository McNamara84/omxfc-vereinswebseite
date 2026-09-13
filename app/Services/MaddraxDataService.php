<?php

namespace App\Services;

use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use App\Services\Maddraxikon\MaddraxikonSnapshotRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service zum Laden von Maddrax-Serien-Daten aus versionierten Datenbank-Snapshots.
 *
 * Bis zum ersten aktivierten Snapshot dienen die bisherigen JSON-Dateien als
 * abwärtskompatibler Rollout-Fallback. Stellt gecachte Zugriffe bereit für:
 * - Maddrax-Hauptserie
 * - Hardcovers
 * - Mission Mars
 * - Das Volk der Tiefe
 * - 2012 - Das Jahr der Apokalypse
 * - Die Abenteurer
 */
class MaddraxDataService
{
    public function __construct(
        private readonly MaddraxikonSnapshotRepository $snapshots,
    ) {}

    /**
     * Cache-TTL in Sekunden (24 Stunden).
     */
    private const CACHE_TTL = 60 * 60 * 24;

    /**
     * Mapping von Serien-Keys zu Dateinamen.
     */
    private const SERIES_FILES = [
        'maddrax' => 'private/maddrax.json',
        'hardcovers' => 'private/hardcovers.json',
        'missionmars' => 'private/missionmars.json',
        'volkdertiefe' => 'private/volkdertiefe.json',
        '2012' => 'private/2012.json',
        'abenteurer' => 'private/abenteurer.json',
    ];

    /**
     * Lädt Daten für eine spezifische Serie (gecacht).
     */
    public function getSeries(string $seriesKey): Collection
    {
        $cacheKey = "maddrax_series_{$seriesKey}";
        $type = BookType::fromKey($seriesKey);

        if ($type !== null) {
            try {
                $snapshotId = $this->snapshots->activeId($type);

                if ($snapshotId !== null) {
                    $cached = Cache::get($cacheKey);

                    if (
                        is_array($cached)
                        && ($cached['snapshot_id'] ?? null) === $snapshotId
                        && isset($cached['rows'])
                        && is_array($cached['rows'])
                        && array_is_list($cached['rows'])
                    ) {
                        return collect($cached['rows']);
                    }

                    $series = $this->snapshots->dataset($snapshotId, $type);
                    Cache::put($cacheKey, [
                        'snapshot_id' => $snapshotId,
                        'rows' => $series,
                    ], self::CACHE_TTL);

                    return collect($series);
                }
            } catch (MaddraxikonCrawlException $exception) {
                Log::critical('MaddraxDataService: Aktiver Datenbank-Snapshot ist nicht lesbar.', [
                    'series' => $seriesKey,
                    'message' => $exception->getMessage(),
                ]);

                return collect();
            }
        }

        $series = Cache::get($cacheKey);

        if (! is_array($series) || ! array_is_list($series)) {
            $series = $this->loadSeriesFromFile($seriesKey)->all();
            Cache::put($cacheKey, $series, self::CACHE_TTL);
        }

        return collect($series);
    }

    /**
     * Lädt alle Serien auf einmal (für Statistik-Seite).
     *
     * @return array<string, Collection>
     */
    public function getAllSeries(): array
    {
        $result = [];

        foreach (array_keys(self::SERIES_FILES) as $key) {
            $result[$key] = $this->getSeries($key);
        }

        return $result;
    }

    /**
     * Lädt die Maddrax-Hauptserie.
     */
    public function getMaddraxRomane(): Collection
    {
        return $this->getSeries('maddrax');
    }

    /**
     * Lädt die Hardcovers.
     */
    public function getHardcovers(): Collection
    {
        return $this->getSeries('hardcovers');
    }

    /**
     * Lädt die Mission Mars-Heftromane.
     */
    public function getMissionMars(): Collection
    {
        return $this->getSeries('missionmars');
    }

    /**
     * Lädt die Das Volk der Tiefe-Heftromane.
     */
    public function getVolkDerTiefe(): Collection
    {
        return $this->getSeries('volkdertiefe');
    }

    /**
     * Lädt die 2012-Heftromane.
     */
    public function get2012(): Collection
    {
        return $this->getSeries('2012');
    }

    /**
     * Lädt die Die Abenteurer-Heftromane.
     */
    public function getAbenteurer(): Collection
    {
        return $this->getSeries('abenteurer');
    }

    /**
     * Erstellt eine Zyklus-Map (Nummer => Zyklus) aus den Maddrax-Romanen.
     *
     * @return Collection<int, string>
     */
    public function getCycleMap(): Collection
    {
        return $this->getMaddraxRomane()->pluck('zyklus', 'nummer');
    }

    /**
     * Invalidiert den Cache für eine oder alle Serien.
     */
    public function clearCache(?string $seriesKey = null): void
    {
        $this->snapshots->clearActiveIdsCache();

        if ($seriesKey === null || $seriesKey === 'maddrax') {
            self::$data = null;
            self::$dataSnapshotId = null;
        }

        if ($seriesKey) {
            Cache::forget("maddrax_series_{$seriesKey}");
        } else {
            Cache::forget('maddrax_all_series');
            foreach (array_keys(self::SERIES_FILES) as $key) {
                Cache::forget("maddrax_series_{$key}");
            }
        }
    }

    /**
     * Lädt Daten aus einer JSON-Datei.
     */
    private function loadSeriesFromFile(string $seriesKey): Collection
    {
        $filename = self::SERIES_FILES[$seriesKey] ?? null;

        if (! $filename) {
            Log::warning("MaddraxDataService: Unbekannter Serien-Key: {$seriesKey}");

            return collect();
        }

        $fullPath = storage_path('app/'.$filename);

        if (! is_readable($fullPath)) {
            // Kein Warning für fehlende optionale Serien-Dateien
            if ($seriesKey !== 'maddrax') {
                return collect();
            }
            Log::warning("MaddraxDataService: Datei nicht gefunden: {$fullPath}");

            return collect();
        }

        $json = file_get_contents($fullPath);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("MaddraxDataService: JSON-Fehler in {$filename}: ".json_last_error_msg());

            return collect();
        }

        return collect($data);
    }

    // ========== Legacy Statische Methoden (Abwärtskompatibilität) ==========

    protected static $data = null;

    protected static ?string $dataSnapshotId = null;

    /**
     * JSON-Daten aus Datei laden (Lazy Loading).
     *
     * @deprecated Nutze stattdessen getMaddraxRomane()
     */
    public static function loadData(): array
    {
        try {
            $snapshots = app(MaddraxikonSnapshotRepository::class);
            $type = BookType::MaddraxDieDunkleZukunftDerErde;
            $snapshotId = $snapshots->activeId($type);

            if ($snapshotId !== null) {
                if (self::$data === null || self::$dataSnapshotId !== $snapshotId) {
                    self::$data = $snapshots->dataset($snapshotId, $type);
                    self::$dataSnapshotId = $snapshotId;
                }

                return self::$data;
            }
        } catch (MaddraxikonCrawlException $exception) {
            Log::critical('MaddraxDataService: Aktiver Datenbank-Snapshot ist nicht lesbar.', [
                'series' => 'maddrax',
                'message' => $exception->getMessage(),
            ]);

            self::$data = [];
            self::$dataSnapshotId = null;

            return self::$data;
        }

        if (is_null(self::$data)) {
            try {
                if (Storage::disk('local')->exists('maddrax.json')) {
                    $json = Storage::disk('local')->get('maddrax.json');
                    self::$data = json_decode($json, true);

                    if (is_null(self::$data)) {
                        Log::warning('MaddraxDataService: JSON konnte nicht dekodiert werden');
                        self::$data = [];
                    }
                } else {
                    Log::warning('MaddraxDataService: maddrax.json wurde nicht gefunden');
                    self::$data = [];
                }
            } catch (\Exception $e) {
                Log::error('MaddraxDataService Fehler: '.$e->getMessage());
                self::$data = [];
            }
        }

        return self::$data;
    }

    /**
     * Alle Autoren distinct und alphabetisch sortiert zurückgeben.
     */
    public static function getAutoren(): array
    {
        $data = self::loadData();

        return collect($data)
            ->pluck('text')
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Alle Zyklen distinct zurückgeben.
     */
    public static function getZyklen(): array
    {
        $data = self::loadData();

        return collect($data)
            ->pluck('zyklus')
            ->flatten()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Alle Nummern und Romantitel zurückgeben.
     */
    public static function getRomane(): array
    {
        $data = self::loadData();

        $titel = collect($data)->pluck('titel')->flatten()->values();
        $nummer = collect($data)->pluck('nummer')->flatten()->values();

        return collect($nummer)->map(function ($item, $key) use ($titel) {
            return $item.' - '.$titel[$key];
        })->toArray();
    }

    /**
     * Alle Figuren distinct und alphabetisch sortiert zurückgeben.
     */
    public static function getFiguren(): array
    {
        $data = self::loadData();

        return collect($data)
            ->pluck('personen')
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Alle Schauplätze distinct und sortiert zurückgeben.
     */
    public static function getSchauplaetze(): array
    {
        $data = self::loadData();

        return collect($data)
            ->pluck('orte')
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Alle Schlagworte distinct und alphabetisch sortiert zurückgeben.
     */
    public static function getSchlagworte(): array
    {
        $data = self::loadData();

        return collect($data)
            ->pluck('schlagworte')
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }
}
