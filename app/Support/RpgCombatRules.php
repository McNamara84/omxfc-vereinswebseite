<?php

namespace App\Support;

final class RpgCombatRules
{
    public const VERSION = 'maddrax-2007-duel-v1';

    public const CATALOG_VERSION = 'maddrax-2007-equipment-v2';

    public const RULEBOOK_SHA256 = '6f70cc6b0f2f22b5a1c7cada0c287e81e56aa1fc0fe3c8516aed2497a24e9cf0';

    public const ROUND_SECONDS = 3;

    /** The first option is the published timeout ruling, never a hidden random choice. */
    public static function rulings(): array
    {
        return [
            'object_material' => self::ruling('SL-16', 'Robustheit des gewählten Objekts', '50', [
                '2' => 'Holz: 2 (Timeout: geringster Tabellenwert)', '3' => 'Plastiflex: 3', '4' => 'Stein: 4', '5' => 'Stahl / Granit: 5', '6' => 'Titan: 6',
            ]),
            'object_damage' => self::ruling('SL-16', 'Auswirkung des Objektschadens', '50', [
                'destroyed' => 'Schutz entfällt bei Zerstörung (7+)', 'severe' => 'Schutz entfällt bereits bei schwerem Schaden (5+)',
            ]),
            'telekinetic_mass' => self::ruling('SL-13', 'Masse des telekinetisch bewegten Ziels', '37', [
                'insufficient' => 'Tragkraft reicht nicht aus', 'sufficient' => 'Tragkraft reicht für dieses Ziel aus',
            ]),
            'tie' => self::ruling('SL-01', 'Gleichstand einer Widerstandsprobe', '12', [
                'unchanged' => 'Bestehender Zustand bleibt erhalten', 'attacker' => 'Die angreifende Seite gewinnt', 'defender' => 'Die verteidigende Seite gewinnt',
            ]),
            'initiative_tie' => self::ruling('SL-02', 'Initiativegleichstand auflösen', '46', [
                'next_round' => 'Diese Runde gleichzeitig, nächste Runde neu würfeln', 'simultaneous' => 'Gleichzeitigkeit für dieses Duell beibehalten',
            ]),
            'psychic_initiative' => self::ruling('SL-03', 'Initiative bei psychischem Auftakt', '46', [
                'Nahkampf' => 'Nahkampf als körperliche Bereitschaft', 'best' => 'Höchste körperliche Kampffertigkeit',
            ]),
            'full_defense' => self::ruling('SL-04', 'Volle Verteidigung erklären', '49', [
                'reactive' => 'Auch bei erster Verteidigung, solange noch kein eigener Angriff erfolgte', 'own_turn' => 'Nur bei eigener Aktionswahl',
            ]),
            'modifiers' => self::ruling('SL-05', 'Verletzungen bei psychischen Proben', '40, 47', [
                'physical' => 'VM nur bei körperlichen Proben', 'concentration' => 'VM auch bei psychischen Proben',
            ]),
            'wounds' => self::ruling('SL-06', 'Mittlere Wunden neben schweren Wunden', '47–48', [
                'persistent' => 'Alle mittleren Wunden paarweise berücksichtigen', 'reset' => 'Einzelne mittlere Wunde bei neuer schwerer Wunde nicht weiter paaren',
            ]),
            'natural_weapons' => self::ruling('SL-07', 'Natürliche Waffen', '25, 41', [
                'weapon' => 'Eigener Waffenmodus mit S +1', 'bonus' => '+1 auf Faustschlag/Tritt, also insgesamt S 0',
            ]),
            'prone' => self::ruling('SL-08', 'Bodenlage nach Sturz', '48–49', [
                'penalty' => '−2 auf Nahkampfangriff, Parade und Ausweichen bis zum Aufstehen', 'none' => 'Nur Bodenlage; kein zusätzlicher Probenabzug',
            ]),
            'reload' => self::ruling('SL-09', 'Nachladen und Ladehemmung', '48–49', [
                'one' => 'Eine Aktion', 'two' => 'Zwei aufeinanderfolgende Aktionen',
            ]),
            'hands' => self::ruling('SL-10', 'Größe und Handbelegung', '40–41, 49', [
                'catalog' => 'Veröffentlichte Größen- und Handbelegungstabelle anwenden',
            ]),
            'psychic_resistance' => self::ruling('SL-11', 'Psychischer Widerstand', '28, 36', [
                'triple' => '2W6 + 3 × WI', 'single' => '2W6 + WI',
            ]),
            'pyrokinetic_rounding' => self::ruling('SL-12', 'Pyrokinese bei ungeradem Fertigkeitswert', '37, 50, 65', [
                'floor' => 'Maximalen Schadensbonus abrunden', 'ceil' => 'Maximalen Schadensbonus aufrunden',
            ]),
            'telekinesis' => self::ruling('SL-13', 'Telekinetische Geschosse', '37', [
                'dodge' => 'Talent + WI gegen Ausweichen, kein zusätzlicher Attributsbonus auf Schaden',
                'resistance' => 'Talent + WI gegen psychischen Widerstand, kein zusätzlicher Attributsbonus auf Schaden',
            ]),
            'duration' => self::ruling('SL-14', 'Psychische Wirkungsdauer', '36–37', [
                'seconds' => 'Exakte Spielsekunden; keine Stapelung gleicher Boni und keine anhaltende Wirkung bei Sofortdauer',
            ]),
            'driller' => self::ruling('SL-15', 'Driller-Nebenwirkung im Zweikampf', '41', [
                'self' => 'Schütze im 3-m-Bereich betroffen; Primärziel nicht doppelt', 'other' => 'Nur weitere Ziele, Schütze ausgenommen',
            ]),
            'context' => self::ruling('SL-16', 'Kreative oder erzählerische Anwendung', '25–26, 45, 50', [
                'none' => 'Keine zusätzliche Zahlenwirkung', 'plus_one' => '+1 auf die aktuelle Probe', 'plus_two' => '+2 auf die aktuelle Probe',
            ]),
            'vulnerability' => self::ruling('SL-16', 'Verwundbarkeit beim aktuellen Schadensauslöser', '26', [
                'normal' => 'Auslöser trifft nicht zu; RO bleibt wirksam', 'vulnerable' => 'Auslöser trifft zu; RO wirkt nicht',
            ]),
            'healing' => self::ruling('SL-17', 'Heilgel und Regeneration', '25, 43, 50', [
                'wound' => 'Heilgel reduziert die schwerste einzelne Wunde um eine Stufe; Regeneration nach voller Spielzeit-Heilfrist',
            ]),
        ];
    }

    public static function defaultRuling(string $key): string
    {
        $definition = self::rulings()[$key] ?? throw new \InvalidArgumentException('Unbekannter Regelfall.');

        return (string) array_key_first($definition['options']);
    }

    private static function ruling(string $id, string $label, string $pages, array $options): array
    {
        return compact('id', 'label', 'pages', 'options');
    }
}
