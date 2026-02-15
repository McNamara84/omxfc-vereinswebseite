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
     * Findet Metadaten mit Fuzzy-Matching.
     *
     * Strategie (in Reihenfolge):
     * 1. Exakter Match (Nummer + Titel)
     * 2. Normalisierter Match (case-insensitive + Sonderzeichen bereinigt)
     * 3. Nummer-Match (eindeutig über alle Serien)
     *
     * @return array{serie: string, serie_name: string, zyklus: string|null, roman: array, match_typ: string}|null
     */
    public function findeMetadatenMitFuzzy(int $nummer, string $titel): ?array
    {
        $normalisiertTitel = $this->normalisiereTitel($titel);
        $nummerTreffer = collect();

        // Alle Serien in einer einzigen Schleife prüfen (exakt + normalisiert + Nummer)
        foreach (self::SERIEN as $serienKey => $serienName) {
            $serie = $this->maddraxDataService->getSeries($serienKey);

            // 1. Exakter Match (Nummer + Titel)
            $roman = $serie->first(fn ($r) => ($r['nummer'] ?? null) === $nummer &&
                ($r['titel'] ?? '') === $titel
            );

            if ($roman) {
                return [
                    'serie' => $serienKey,
                    'serie_name' => $serienName,
                    'zyklus' => $roman['zyklus'] ?? null,
                    'roman' => $roman,
                    'match_typ' => 'exakt',
                ];
            }

            // 2. Normalisierter Match (case-insensitive, Sonderzeichen bereinigt)
            $roman = $serie->first(fn ($r) => ($r['nummer'] ?? null) === $nummer &&
                $this->normalisiereTitel($r['titel'] ?? '') === $normalisiertTitel
            );

            if ($roman) {
                return [
                    'serie' => $serienKey,
                    'serie_name' => $serienName,
                    'zyklus' => $roman['zyklus'] ?? null,
                    'roman' => $roman,
                    'match_typ' => 'normalisiert',
                ];
            }

            // 3. Nummern-Treffer sammeln (für späteren eindeutigen Match)
            $romane = $serie->filter(fn ($r) => ($r['nummer'] ?? null) === $nummer);
            foreach ($romane as $r) {
                $nummerTreffer->push([
                    'serie' => $serienKey,
                    'serie_name' => $serienName,
                    'nummer' => $r['nummer'],
                    'titel' => $r['titel'] ?? '',
                    'zyklus' => $r['zyklus'] ?? null,
                ]);
            }
        }

        // 3. Nummer-Match (nur wenn eindeutig über alle Serien)
        if ($nummerTreffer->count() === 1) {
            $match = $nummerTreffer->first();

            return [
                'serie' => $match['serie'],
                'serie_name' => $match['serie_name'],
                'zyklus' => $match['zyklus'],
                'roman' => $match,
                'match_typ' => 'nummer',
            ];
        }

        return null;
    }

    /**
     * Normalisiert einen Titel für Fuzzy-Vergleich.
     * Lowercase, Sonderzeichen entfernt, mehrfache Leerzeichen normalisiert.
     */
    public function normalisiereTitel(string $titel): string
    {
        $titel = mb_strtolower($titel);
        $titel = preg_replace('/[^\p{L}\p{N}\s]/u', '', $titel) ?? $titel;

        return preg_replace('/\s+/', ' ', trim($titel)) ?? $titel;
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
     * @return Collection<string, array{name: string, min: int, max: int, anzahl: int, bandbereich: string}>
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
     * Erstellt eine zusammengefasste Serien-Übersicht für die Kompendium-Startseite.
     *
     * Maddrax-Zyklen werden unter einem Eintrag zusammengefasst:
     * - Vollständige Zyklen: nur Name (z.B. "Euree-Zyklus")
     * - Unvollständige Zyklen: Name + Bandbereich (z.B. "Expeditionszyklus Band 50-55, 57-74")
     * - Miniserien: einfach Serienname + Bandbereich
     *
     * @return Collection<int, array{serie: string, serie_name: string, beschreibung: string, anzahl: int}>
     */
    public function getZusammengefassteUebersicht(): Collection
    {
        $indexierte = KompendiumRoman::indexiert()
            ->orderBy('serie')
            ->orderBy('roman_nr')
            ->get();

        if ($indexierte->isEmpty()) {
            return collect();
        }

        $nachSerie = $indexierte->groupBy('serie');
        $ergebnis = collect();

        foreach ($nachSerie as $serienKey => $romane) {
            $serienName = $this->getSerienName($serienKey);

            if ($serienKey === 'maddrax') {
                $beschreibung = $this->formatMaddraxZyklenBeschreibung($romane);
                $ergebnis->push([
                    'serie' => $serienKey,
                    'serie_name' => 'Maddrax',
                    'beschreibung' => $beschreibung,
                    'anzahl' => $romane->count(),
                ]);
            } else {
                $nummern = $romane->pluck('roman_nr')->sort()->values();
                $bandbereich = $this->formatBandbereich($nummern);
                $ergebnis->push([
                    'serie' => $serienKey,
                    'serie_name' => $serienName,
                    'beschreibung' => "Band {$bandbereich}",
                    'anzahl' => $romane->count(),
                ]);
            }
        }

        return $ergebnis;
    }

    /**
     * Formatiert die Zyklen-Beschreibung für die Maddrax-Hauptserie.
     *
     * Vollständige Zyklen werden nur mit Namen angezeigt,
     * unvollständige mit Bandbereich.
     */
    private function formatMaddraxZyklenBeschreibung(Collection $romane): string
    {
        $nachZyklus = $romane->groupBy(fn ($r) => $r->zyklus ?? 'Ohne Zyklus');
        $teile = [];

        // Soll-Anzahl pro Zyklus einmal vorberechnen (vermeidet wiederholte Cache-Reads)
        $sollProZyklus = $this->maddraxDataService->getSeries('maddrax')
            ->groupBy('zyklus')
            ->map->count();

        foreach ($nachZyklus as $zyklus => $zyklusRomane) {
            if ($zyklus === 'Ohne Zyklus') {
                $nummern = $zyklusRomane->pluck('roman_nr')->sort()->values();
                $teile[] = 'Band '.$this->formatBandbereich($nummern);

                continue;
            }

            $istVollstaendig = $this->istZyklusVollstaendig($zyklus, $zyklusRomane, $sollProZyklus);

            if ($istVollstaendig) {
                $teile[] = "{$zyklus}-Zyklus";
            } else {
                $nummern = $zyklusRomane->pluck('roman_nr')->sort()->values();
                $teile[] = "{$zyklus}-Zyklus Band ".$this->formatBandbereich($nummern);
            }
        }

        if (empty($teile)) {
            return '';
        }

        if (count($teile) === 1) {
            return $teile[0];
        }

        $letzter = array_pop($teile);

        return implode(', ', $teile).' und '.$letzter;
    }

    /**
     * Prüft ob alle Romane eines Zyklus der Maddrax-Hauptserie indexiert sind.
     */
    private function istZyklusVollstaendig(string $zyklus, Collection $indexierteRomane, Collection $sollProZyklus): bool
    {
        $sollAnzahl = $sollProZyklus->get($zyklus, 0);

        if ($sollAnzahl === 0) {
            return false;
        }

        return $indexierteRomane->count() >= $sollAnzahl;
    }

    /**
     * Berechnet den Indexierungsfortschritt pro Zyklus/Serie.
     *
     * @return Collection<int, array{zyklus: string, serie: string, soll: int, ist: int, prozent: float, status: string}>
     */
    public function getZyklenFortschritt(): Collection
    {
        $fortschritt = collect();

        // Alle indexierten Romane einmal laden und nach Serie gruppieren (vermeidet N+1)
        $alleIndexierten = KompendiumRoman::indexiert()->get()->groupBy('serie');

        foreach (self::SERIEN as $serienKey => $serienName) {
            $sollRomane = $this->maddraxDataService->getSeries($serienKey);
            $istRomane = $alleIndexierten->get($serienKey, collect());

            if ($serienKey === 'maddrax') {
                // Pro Zyklus aufteilen
                $zyklen = $sollRomane->groupBy('zyklus');
                foreach ($zyklen as $zyklus => $sollInZyklus) {
                    // Explizite Null-Behandlung: groupBy kann null-Keys erzeugen
                    $istInZyklus = $zyklus === '' || $zyklus === null
                        ? $istRomane->whereNull('zyklus')
                        : $istRomane->where('zyklus', $zyklus);
                    $prozent = $sollInZyklus->count() > 0
                        ? round(($istInZyklus->count() / $sollInZyklus->count()) * 100, 1)
                        : 0;

                    $fortschritt->push([
                        'zyklus' => ($zyklus === '' || $zyklus === null) ? 'Ohne Zyklus' : $zyklus,
                        'serie' => $serienName,
                        'soll' => $sollInZyklus->count(),
                        'ist' => $istInZyklus->count(),
                        'prozent' => $prozent,
                        'status' => match (true) {
                            $prozent >= 100 => 'vollstaendig',
                            $prozent > 0 => 'teilweise',
                            default => 'leer',
                        },
                    ]);
                }
            } else {
                $prozent = $sollRomane->count() > 0
                    ? round(($istRomane->count() / $sollRomane->count()) * 100, 1)
                    : 0;

                $fortschritt->push([
                    'zyklus' => $serienName,
                    'serie' => $serienName,
                    'soll' => $sollRomane->count(),
                    'ist' => $istRomane->count(),
                    'prozent' => $prozent,
                    'status' => match (true) {
                        $prozent >= 100 => 'vollstaendig',
                        $prozent > 0 => 'teilweise',
                        default => 'leer',
                    },
                ]);
            }
        }

        return $fortschritt;
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
