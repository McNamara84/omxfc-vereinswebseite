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
            ->pluck('text')       // extrahiere alle "text"-Arrays
            ->flatten()           // flach machen
            ->unique()            // doppelte Einträge entfernen
            ->sort()              // alphabetisch sortieren
            ->values()            // Werte zurücksetzen (indexbasiert)
            ->toArray();

        return $autoren;
    }
}
