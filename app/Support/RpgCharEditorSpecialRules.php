<?php

namespace App\Support;

final class RpgCharEditorSpecialRules
{
    public const DEFAULT_CREATION_LEVEL = 3;

    /** @deprecated Use DEFAULT_CREATION_LEVEL or creationLevel() for level-dependent rules. */
    public const CREATION_LEVEL = self::DEFAULT_CREATION_LEVEL;

    public const BASE_ATTRIBUTE_POINTS = 2;

    public const MAX_EXTRA_ATTRIBUTE_POINTS = 1;

    public const FREE_ADVANTAGE_UNITS = 1;

    public const MAX_EXTRA_ADVANTAGE_UNITS = 2;

    /**
     * Figurenstärken according to Regelwerk 2007, page 15.
     *
     * @var array<int, array<string, mixed>>
     */
    public const CREATION_LEVELS = [
        1 => [
            'level' => 1,
            'attributePoints' => 0,
            'skillPoints' => 10,
            'skillMax' => 3,
            'freeAdvantageUnits' => 0,
            'automaticAdvantages' => [],
            'automaticDisadvantages' => ['Taratzenfutter'],
        ],
        2 => [
            'level' => 2,
            'attributePoints' => 1,
            'skillPoints' => 15,
            'skillMax' => 3,
            'freeAdvantageUnits' => 0,
            'automaticAdvantages' => [],
            'automaticDisadvantages' => [],
        ],
        3 => [
            'level' => 3,
            'attributePoints' => 2,
            'skillPoints' => 20,
            'skillMax' => 4,
            'freeAdvantageUnits' => 1,
            'automaticAdvantages' => ['Zäh'],
            'automaticDisadvantages' => [],
        ],
        4 => [
            'level' => 4,
            'attributePoints' => 3,
            'skillPoints' => 40,
            'skillMax' => 5,
            'freeAdvantageUnits' => 2,
            'automaticAdvantages' => ['Zäh'],
            'automaticDisadvantages' => [],
        ],
        5 => [
            'level' => 5,
            'attributePoints' => 4,
            'skillPoints' => 60,
            'skillMax' => 6,
            'freeAdvantageUnits' => 3,
            'automaticAdvantages' => ['Zäh'],
            'automaticDisadvantages' => [],
        ],
    ];

    public const ATTRIBUTE_TARGETS = ['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'];

    public const SENSE_TARGETS = ['Sehen', 'Hören', 'Riechen', 'Schmecken', 'Tasten'];

    public const PSYCHIC_POWER_TARGETS = [
        'Beherrschung',
        'Empathie',
        'Gedankenschild',
        'Pyrokinese',
        'Telepathie',
        'Telekinese',
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function advantages(): array
    {
        return [
            'Anführer' => self::advantage('11-12', [[11, 12]], 'Natürlicher Anführer; +2 auf Proben, um Leute zu befehligen oder zu überzeugen.'),
            'Gestaltwandler' => self::advantage('13', [[13, 13]], 'Kann Gestalt und Stimme verändern (Statur und Größe ±20 %, Haarfarbe und -länge, Augenfarbe, Hautfarbe und Geschlecht); zählt bei der Erschaffung wie drei Vorteile.', cost: 3, effect: 'shapechanger'),
            'Gesteigertes Attribut' => self::advantage('14-24', [[14, 24]], '+1 auf ein Attribut nach Wahl.', repeat: 'unique_target', targets: self::ATTRIBUTE_TARGETS, requiresJustification: true, detailPlaceholder: 'Ursprung der Steigerung begründen', effect: 'attribute_bonus'),
            'Gesteigerter Sinn' => self::advantage('25-26', [[25, 26]], '+3 auf Wahrnehmungsproben mit einem gewählten Sinn.', repeat: 'unique_target', targets: self::SENSE_TARGETS, requiresJustification: true, detailPlaceholder: 'Ursprung der Sinnessteigerung begründen', effect: 'sense_bonus'),
            'High-Tech-Ausrüstung' => self::advantage('31-32', [[31, 32]], 'Besitzt vier High-Tech-Gegenstände; SL-Zustimmung erforderlich.', effect: 'high_tech_equipment'),
            'Kampfreflexe' => self::advantage('33-34', [[33, 34]], '+2 Bonus auf alle Ausweichen-Proben.', effect: 'dodge_bonus'),
            'Kaltblütig' => self::advantage('35-36', [[35, 36]], '+1 Bonus auf alle Verteidigungswürfe.', effect: 'defense_bonus'),
            'Kiemen' => self::advantage('41', [[41, 41]], 'Kann beliebig lange unter Wasser atmen.', effect: 'gills'),
            'Kind zweier Welten' => self::advantage('42', [[42, 42]], 'Kann sowohl Bildung als auch Intuition lernen.', effect: 'education_intuition_exception'),
            'Nachtsicht' => self::advantage('43-44', [[43, 44]], 'Kann ohne Abzüge im Dunkeln sehen.', requiresJustification: true, detailPlaceholder: 'Ursprung der Nachtsicht begründen', effect: 'night_vision'),
            'Natürliche Waffen' => self::advantage('45', [[45, 45]], 'Natürliche Angriffe verwenden Nahkampf und verursachen +1 S Schaden.', requiresJustification: true, detailPlaceholder: 'Art und Ursprung der natürlichen Waffe', effect: 'natural_weapons'),
            'Panzerung' => self::advantage('46', [[46, 46]], 'Besitzt Schutzfaktor 1; mehrfach wählbar und additiv.', repeat: 'stack', requiresJustification: true, detailPlaceholder: 'Ursprung der Panzerung begründen', effect: 'armor'),
            'Psychische Kraft' => self::advantage('51', [[51, 51]], 'Erhält eine konkrete psychische Kraft.', targets: self::PSYCHIC_POWER_TARGETS, detailPlaceholder: 'Psychische Kraft wählen', effect: 'psychic_power'),
            'Psychisches Reservoir' => self::advantage('52', [[52, 52]], 'Höchster psychischer FW zählt bei der PEP-Ermittlung doppelt.', effect: 'psychic_reservoir'),
            'Regeneration' => self::advantage('53', [[53, 53]], 'Heilt je Instanz mit einem weiteren Faktor 10.', repeat: 'stack', requiresJustification: true, detailPlaceholder: 'Ursprung der Regeneration begründen', effect: 'regeneration'),
            'Scharfschütze' => self::advantage('54', [[54, 54]], '+1 auf Fernkampfangriffe und +1 Schaden in Kernschussreichweite.', effect: 'marksman'),
            'Schnell' => self::advantage('55-56', [[55, 56]], '+2 auf Grundbewegungsweite und +1 auf Initiative.', requiresJustification: true, detailPlaceholder: 'Ursprung der Schnelligkeit begründen', effect: 'speed'),
            'Sprachbegabt' => self::advantage('61', [[61, 61]], 'Kann Sprachen und Dialekte ohne Hilfe lernen und beherrscht bis zu drei pro Fertigkeitspunkt.', effect: 'language_capacity'),
            'Tiergefährte' => self::advantage('62-64', [[62, 64]], 'Erhält mit SL-Zustimmung ein Tier als dauerhaften und loyalen Begleiter; empathische Verständigung ist möglich.', requiresDetail: true, detailPlaceholder: 'Tier und Besonderheit notieren', effect: 'animal_companion'),
            'Zäh' => self::advantage('65-66', [[65, 66]], 'Schutzfaktor +1 durch Zähigkeit und Heldentum; ab Figurenstärke 3 automatisch.', effect: 'toughness'),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function disadvantages(): array
    {
        return [
            'Abergläubisch' => self::disadvantage('11-16', [[11, 16]], 'Muss mindestens drei Eigenarten wählen, die das tägliche Handeln beeinflussen.', true, 'Mindestens drei Eigenarten notieren'),
            'Abhängige' => self::disadvantage('21', [[21, 21]], 'Muss ständig Verwandte oder Familie beschützen.', true, 'Person oder Familie notieren'),
            'Anfälligkeit gegen Wahnsinn' => self::disadvantage('22', [[22, 22]], 'Wahnsinn tritt bei bestimmten Bedingungen ein; der SL übernimmt den Charakter für die Dauer des Anfalls.', true, 'Konkreten Auslöser notieren'),
            'Auffällig' => self::disadvantage('23-24', [[23, 24]], 'Ist wegen ungewöhnlichen Aussehens leicht zu erkennen und erhält -4 auf alle Verkleiden-Proben.'),
            'Blutdurst' => self::disadvantage('25', [[25, 25]], 'Benötigt alle 24 Stunden frisches Blut oder erleidet einen kumulativen Abzug von -1 auf alle Proben.'),
            'Ehrenkodex' => self::disadvantage('26-36', [[26, 36]], 'Folgt einem definierenden Ehrenkodex, der das tägliche Handeln einschränkt.', true, 'Kodex notieren'),
            'Feind' => self::disadvantage('41-44', [[41, 44]], 'Ist mit einem Volk oder einer mächtigen Person verfeindet, die das eigene Leben kontinuierlich bedroht.', true, 'Volk, Gruppe oder Person notieren'),
            'Gejagt' => self::disadvantage('45-46', [[45, 46]], 'Wird von fast allen Völkern gehasst und gejagt und kann sich kaum frei in Städten und Dörfern bewegen.', true, 'Verfolger notieren'),
            'Lichtscheu' => self::disadvantage('51', [[51, 51]], 'Erleidet -2 auf alle Proben, wenn die Haut hellem Licht ausgesetzt ist.'),
            'Primitiv' => self::disadvantage('52-53', [[52, 53]], 'Kann niemals Bildung lernen und keine technischen Gerätschaften benutzen.'),
            'Taratzenfutter' => self::disadvantage('54-63', [[54, 63]], 'Alle Schadenswürfe werden um 1 erhöht.'),
            'Tödliche Immunschwäche' => self::disadvantage('64', [[64, 64]], 'Stirbt in 1W6 Stunden, wenn ohne Schutzanzug Kontakt mit der Oberflächenwelt besteht.'),
            'Verpflichtung' => self::disadvantage('65', [[65, 65]], 'Ist einer Organisation, Gruppe oder Person verpflichtet, die über einen nennenswerten Teil der eigenen Zeit bestimmen kann.', true, 'Organisation, Gruppe oder Person notieren'),
            'Verwundbarkeit' => self::disadvantage('66', [[66, 66]], 'Wird durch ein bestimmtes Mittel besonders schwer verwundet; Robustheit zählt nicht gegen Schaden.', true, 'Mittel oder Quelle notieren'),
        ];
    }

    public static function ruleConfig(): array
    {
        $advantages = self::advantages();
        $disadvantages = self::disadvantages();
        $defaultLevel = self::creationLevel(self::DEFAULT_CREATION_LEVEL);

        return [
            'creation' => [
                // Keep the former level-3 fields for clients that have not yet
                // switched to the level table.
                'level' => self::DEFAULT_CREATION_LEVEL,
                'defaultLevel' => self::DEFAULT_CREATION_LEVEL,
                'levels' => array_values(self::CREATION_LEVELS),
                'baseAttributePoints' => $defaultLevel['attributePoints'],
                'maxExtraAttributePoints' => self::MAX_EXTRA_ATTRIBUTE_POINTS,
                'freeAdvantageUnits' => $defaultLevel['freeAdvantageUnits'],
                'maxExtraAdvantageUnits' => self::MAX_EXTRA_ADVANTAGE_UNITS,
            ],
            'advantages' => array_keys($advantages),
            'disadvantages' => array_keys($disadvantages),
            'advantageRules' => $advantages,
            'disadvantageRules' => $disadvantages,
            'advantageCosts' => array_map(static fn (array $rule): int => $rule['cost'], $advantages),
            'repeatableAdvantages' => array_keys(array_filter($advantages, static fn (array $rule): bool => $rule['repeat'] !== 'none')),
            'advantageDetailRequired' => array_keys(array_filter($advantages, static fn (array $rule): bool => $rule['requires_detail'])),
            'disadvantageDetailRequired' => array_keys(array_filter($disadvantages, static fn (array $rule): bool => $rule['requires_detail'])),
        ];
    }

    /** @return list<int> */
    public static function validCreationLevels(): array
    {
        return array_keys(self::CREATION_LEVELS);
    }

    /** @return array<string, mixed> */
    public static function creationLevel(int $level): array
    {
        return self::CREATION_LEVELS[$level] ?? self::CREATION_LEVELS[self::DEFAULT_CREATION_LEVEL];
    }

    private static function advantage(
        string $w66,
        array $ranges,
        string $description,
        int $cost = 1,
        string $repeat = 'none',
        array $targets = [],
        bool $requiresDetail = false,
        bool $requiresJustification = false,
        string $detailPlaceholder = '',
        string $effect = 'note',
    ): array {
        return compact('w66', 'ranges', 'description', 'cost', 'targets', 'effect') + [
            'repeat' => $repeat,
            'requires_detail' => $requiresDetail,
            'requires_justification' => $requiresJustification,
            'detail_placeholder' => $detailPlaceholder,
        ];
    }

    private static function disadvantage(
        string $w66,
        array $ranges,
        string $description,
        bool $requiresDetail = false,
        string $detailPlaceholder = '',
    ): array {
        return compact('w66', 'ranges', 'description') + [
            'requires_detail' => $requiresDetail,
            'detail_placeholder' => $detailPlaceholder,
        ];
    }
}
