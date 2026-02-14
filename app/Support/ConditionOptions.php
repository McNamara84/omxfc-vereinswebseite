<?php

namespace App\Support;

/**
 * Zentralisierte Zustandsoptionen für die Romantauschbörse.
 *
 * Liefert Condition-Option-Arrays im maryUI-x-select-Format (['id' => ..., 'name' => ...]).
 * Nutzt die Übersetzungsschlüssel aus lang/de/romantausch.php → condition.*.
 *
 * Zustandsskala (Z0 = bester, Z4 = schlechtester):
 * - Ganzzahlige Werte (Z0, Z1, Z2, Z3) beschreiben eindeutige Zustände
 * - Zwischenwerte (Z0-1, Z1-2, Z2-3) liegen zwischen zwei Stufen
 *
 * @see resources/views/components/condition-select-options.blade.php (legacy, für raw <select>)
 * @see lang/de/romantausch.php
 */
class ConditionOptions
{
    /**
     * Standard-Zustände (Z0 bis Z3) – für Bundle-Min-Auswahl.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function standard(): array
    {
        return [
            ['id' => 'Z0', 'name' => 'Z0 - ' . __('romantausch.condition.Z0')],
            ['id' => 'Z0-1', 'name' => 'Z0-1 - ' . __('romantausch.condition.Z0-1'), 'title' => __('romantausch.condition.Z0-1_title')],
            ['id' => 'Z1', 'name' => 'Z1 - ' . __('romantausch.condition.Z1')],
            ['id' => 'Z1-2', 'name' => 'Z1-2 - ' . __('romantausch.condition.Z1-2'), 'title' => __('romantausch.condition.Z1-2_title')],
            ['id' => 'Z2', 'name' => 'Z2 - ' . __('romantausch.condition.Z2')],
            ['id' => 'Z2-3', 'name' => 'Z2-3 - ' . __('romantausch.condition.Z2-3'), 'title' => __('romantausch.condition.Z2-3_title')],
            ['id' => 'Z3', 'name' => 'Z3 - ' . __('romantausch.condition.Z3')],
        ];
    }

    /**
     * Alle Zustände (Z0 bis Z4) – für Einzel-Angebote und -Gesuche.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function full(): array
    {
        return [
            ...self::standard(),
            ['id' => 'Z3-4', 'name' => 'Z3-4 - ' . __('romantausch.condition.Z3-4'), 'title' => __('romantausch.condition.Z3-4_title')],
            ['id' => 'Z4', 'name' => 'Z4 - ' . __('romantausch.condition.Z4')],
        ];
    }

    /**
     * Alle Zustände mit vorangestellter "Gleicher Zustand"-Option – für Bundle-Max-Auswahl.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function withSameOption(): array
    {
        return [
            ['id' => '', 'name' => __('romantausch.condition.same')],
            ...self::full(),
        ];
    }
}
