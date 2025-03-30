<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;


class MaddraxDataService
{
    protected static $data = null;

    /**
     * JSON-Daten aus Datei laden (Lazy Loading)
     */
    public static function loadData(): array
    {
        if (is_null(self::$data)) {
            $json = Storage::disk('local')->get('maddrax.json');
            self::$data = json_decode($json, true);
        }

        return self::$data;
    }

    /**
     * Alle Autoren distinct und alphabetisch sortiert zurückgeben
     */
    public static function getAutoren(): array
    {
        $data = self::loadData();

        $autoren = collect($data)
            ->pluck('text')  // extrahiere alle "text"-Arrays
            ->flatten()             // flach machen
            ->unique()              // doppelte Einträge entfernen
            ->sort()                // alphabetisch sortieren
            ->values()              // Werte zurücksetzen (indexbasiert)
            ->toArray();

        return $autoren;
    }

    /**
     * Alle Zyklen distinct zurückgeben
     */
    public static function getZyklen(): array
    {
        $data = self::loadData();

        $zyklen = collect($data)
            ->pluck('zyklus')    // extrahiere alle "zyklus"-Arrays
            ->flatten()                 // flach machen
            ->unique()                  // doppelte Einträge entfernen
            ->values()                  // Werte zurücksetzen (indexbasiert)
            ->toArray();

        return $zyklen;
    }

    /**
     * Alle Nummern und Romantitel zurückgeben
     */
    public static function getRomane(): array
    {
        $data = self::loadData();

        $titel = collect($data)
            ->pluck('titel')    // extrahiere alle "titel"-Arrays
            ->flatten()                // flach machen
            ->values();                // Werte zurücksetzen (indexbasiert)
        $nummer = collect($data)
            ->pluck('nummer')    // extrahiere alle "nummer"-Arrays
            ->flatten()                // flach machen
            ->values();                // Werte zurücksetzen (indexbasiert)
        // Nummer und Titel jeweils zu einem String zusammenfügen
        $romane = collect($nummer)->map(function ($item, $key) use ($titel) {
            return $item . ' - ' . $titel[$key];
        })->toArray(); // in Array umwandeln



        return $romane;
    }

    /**
     * Alle Figuren distinct und sortiert nach Bewertung der Romane zurückgeben
     */
    public static function getFiguren(): array
    {
        $data = self::loadData();

        $figuren = collect($data)
            ->pluck('personen')  // extrahiere alle "figuren"-Arrays
            ->flatten()                 // flach machen
            ->unique()                  // doppelte Einträge entfernen
            ->sort()                    // alphabetisch sortieren
            ->values()                  // Werte zurücksetzen (indexbasiert)
            ->toArray();

        return $figuren;
    }
}
