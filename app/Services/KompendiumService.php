<?php

namespace App\Services;

use App\Models\KompendiumRoman;
use Illuminate\Support\Collection;

/**
 * Service für die Kompendium-Verwaltung.
 *
 * Verantwortlichkeiten:
 * - Dateinamen parsen (Nummer + Titel extrahieren)
 * - Metadaten aus MaddraxDataService abrufen
 * - Statistiken und gruppierte Daten bereitstellen
 */
class KompendiumService
{
    /**
     * Mapping von Serien-Keys zu Anzeigenamen.
     */
    public const SERIEN = [
        'maddrax' => 'Maddrax - Die dunkle Zukunft der Erde',
        'hardcovers' => 'Hardcovers',
        'missionmars' => 'Mission Mars',
        'volkdertiefe' => 'Das Volk der Tiefe',
        '2012' => '2012 - Das Jahr der Apokalypse',
        'abenteurer' => 'Die Abenteurer',
    ];

    public function __construct(
        private readonly MaddraxDataService $maddraxDataService
    ) {}

    /**
     * Parst einen Dateinamen im Format "001 - Der Gott aus dem Eis.txt".
     *
     * @return array{nummer: int, titel: string}|null
     */
    public function parseDateiname(string $dateiname): ?array
    {
        $name = pathinfo($dateiname, PATHINFO_FILENAME);

        if (! preg_match('/^(\d+)\s*-\s*(.+)$/', $name, $matches)) {
            return null;
        }

        return [
            'nummer' => (int) $matches[1],
            'titel' => trim($matches[2]),
        ];
    }

    /**
     * Findet Metadaten (Zyklus) für einen Roman aus allen Serien.
     *
     * @return array{serie: string, serie_name: string, zyklus: string|null, roman: array}|null
     */
    public function findeMetadaten(int $nummer, string $titel): ?array
    {
        foreach (self::SERIEN as $serienKey => $serienName) {
            $serie = $this->maddraxDataService->getSeries($serienKey);

            $roman = $serie->first(fn ($r) => ($r['nummer'] ?? null) === $nummer &&
                ($r['titel'] ?? '') === $titel
            );

            if ($roman) {
                return [
                    'serie' => $serienKey,
                    'serie_name' => $serienName,
                    'zyklus' => $roman['zyklus'] ?? null,
                    'roman' => $roman,
                ];
            }
        }

        return null;
    }

    /**
     * Sucht nach möglichen Übereinstimmungen nur anhand der Nummer.
     * Nützlich wenn der Titel leicht abweicht.
     *
     * @return Collection<array{serie: string, serie_name: string, nummer: int, titel: string, zyklus: string|null}>
     */
    public function findeRomaneNachNummer(int $nummer): Collection
    {
        $treffer = collect();

        foreach (self::SERIEN as $serienKey => $serienName) {
            $serie = $this->maddraxDataService->getSeries($serienKey);

            $romane = $serie->filter(fn ($r) => ($r['nummer'] ?? null) === $nummer);

            foreach ($romane as $roman) {
                $treffer->push([
                    'serie' => $serienKey,
                    'serie_name' => $serienName,
                    'nummer' => $roman['nummer'],
                    'titel' => $roman['titel'] ?? '',
                    'zyklus' => $roman['zyklus'] ?? null,
                ]);
            }
        }

        return $treffer;
    }

    /**
     * Gruppiert indexierte Romane nach Zyklus/Serie für die Anzeige.
     *
     * @return Collection<string, Collection<KompendiumRoman>>
     */
    public function getIndexierteRomaneGruppiert(): Collection
    {
        return KompendiumRoman::indexiert()
            ->orderBy('serie')
            ->orderBy('roman_nr')
            ->get()
            ->groupBy(fn ($r) => $r->zyklus ?? $this->getSerienName($r->serie));
    }

    /**
     * Gibt die Liste aller verfügbaren Serien zurück.
     *
     * @return array<string, string>
     */
    public function getSerienListe(): array
    {
        return self::SERIEN;
    }

    /**
     * Gibt den Anzeigenamen einer Serie zurück.
     */
    public function getSerienName(string $serienKey): string
    {
        return self::SERIEN[$serienKey] ?? $serienKey;
    }

    /**
     * Erstellt eine Zusammenfassung der indexierten Romane für die Anzeige.
     *
     * @return Collection<string, array{name: string, min: int, max: int, anzahl: int}>
     */
    public function getIndexierteRomaneSummary(): Collection
    {
        $gruppiert = $this->getIndexierteRomaneGruppiert();

        return $gruppiert->map(function ($romane, $gruppe) {
            $nummern = $romane->pluck('roman_nr')->sort()->values();

            return [
                'name' => $gruppe,
                'min' => $nummern->min(),
                'max' => $nummern->max(),
                'anzahl' => $romane->count(),
                'bandbereich' => $this->formatBandbereich($nummern),
            ];
        });
    }

    /**
     * Formatiert eine Liste von Nummern als kompakten Bereich.
     * z.B. [1,2,3,5,6,10] => "1-3, 5-6, 10"
     */
    private function formatBandbereich(Collection $nummern): string
    {
        if ($nummern->isEmpty()) {
            return '';
        }

        $sorted = $nummern->sort()->values()->all();
        $bereiche = [];
        $start = $sorted[0];
        $end = $sorted[0];

        for ($i = 1; $i < count($sorted); $i++) {
            if ($sorted[$i] === $end + 1) {
                $end = $sorted[$i];
            } else {
                $bereiche[] = $start === $end ? (string) $start : "{$start}-{$end}";
                $start = $sorted[$i];
                $end = $sorted[$i];
            }
        }

        $bereiche[] = $start === $end ? (string) $start : "{$start}-{$end}";

        return implode(', ', $bereiche);
    }

    /**
     * Gibt Statistiken über das Kompendium zurück.
     *
     * @return array{gesamt: int, indexiert: int, in_bearbeitung: int, fehler: int, hochgeladen: int}
     */
    public function getStatistiken(): array
    {
        return [
            'gesamt' => KompendiumRoman::count(),
            'indexiert' => KompendiumRoman::where('status', 'indexiert')->count(),
            'in_bearbeitung' => KompendiumRoman::where('status', 'indexierung_laeuft')->count(),
            'fehler' => KompendiumRoman::where('status', 'fehler')->count(),
            'hochgeladen' => KompendiumRoman::where('status', 'hochgeladen')->count(),
        ];
    }
}
