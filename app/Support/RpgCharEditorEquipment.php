<?php

namespace App\Support;

final class RpgCharEditorEquipment
{
    public const ITEM_LIMIT = 6;

    public const HIGH_TECH_ITEM_LIMIT = 4;

    public const MAX_ITEMS = 64;

    public const QUANTITY_MAX = 20;

    public const HIGH_TECH_ADVANTAGE = 'High-Tech-Ausrüstung';

    public static function ruleConfig(): array
    {
        return [
            'limits' => [
                'items' => self::ITEM_LIMIT,
                'highTechItems' => self::HIGH_TECH_ITEM_LIMIT,
                'maxRows' => self::MAX_ITEMS,
                'maxQuantity' => self::QUANTITY_MAX,
            ],
            'categories' => self::categories(),
            'clothing' => self::clothing(),
            'items' => self::items(),
        ];
    }

    public static function categories(): array
    {
        return [
            'melee_weapons' => 'Nahkampfwaffen',
            'ranged_weapons' => 'Fernkampfwaffen',
            'armor' => 'Rüstungen',
            'shields' => 'Schilde',
            'low_tech' => 'Low-Tech-Gegenstände',
            'high_tech' => 'High-Tech-Gegenstände',
            'transport' => 'Fortbewegungsmittel',
        ];
    }

    public static function clothing(): array
    {
        return [
            ['id' => 'kleidung-einfach', 'name' => 'Kleidung, einfach', 'description' => 'Leinenhose oder -rock, Bluse oder Hemd, Sandalen, einfacher Gürtel, Mütze, leichte Jacke.', 'tw' => '4', 'bucks' => '10'],
            ['id' => 'kleidung-wanderer', 'name' => 'Kleidung, Wanderer', 'description' => 'Wie einfache Kleidung, aber aus Leder und Wolle, Mantel, Stiefel statt Sandalen, Handschuhe.', 'tw' => '8', 'bucks' => '40'],
            ['id' => 'kleidung-adeliger', 'name' => 'Kleidung, Adeliger', 'description' => 'Sehr wertvolle Kleidung aus Seide und Fell.', 'tw' => '32', 'bucks' => '1000'],
        ];
    }

    public static function clothingMap(): array
    {
        return array_column(self::clothing(), null, 'id');
    }

    public static function itemMap(): array
    {
        return array_column(self::items(), null, 'id');
    }

    public static function requiresHighTechAdvantage(array $item): bool
    {
        return (bool) ($item['requiresHighTechAdvantage'] ?? false)
            || ($item['category'] ?? null) === 'high_tech';
    }

    public static function countsTowardLimit(array $item): bool
    {
        return ($item['countsTowardLimit'] ?? true) !== false;
    }

    public static function items(): array
    {
        return [
            ['id' => 'faustschlag-tritt', 'name' => 'Faustschlag / Tritt', 'category' => 'melee_weapons', 'summary' => 'ST, P 0, S -1', 'tw' => '-', 'bucks' => '-', 'countsTowardLimit' => false],
            ['id' => 'schlagring-stein', 'name' => 'Schlagring / Stein', 'category' => 'melee_weapons', 'summary' => 'ST, P 0, S +0', 'tw' => '-', 'bucks' => '-'],
            ['id' => 'messer-dolch', 'name' => 'Messer / Dolch', 'category' => 'melee_weapons', 'summary' => 'GE, P 0, S +0, Wurf E, RI 2m, MR 10m', 'tw' => '2', 'bucks' => '2'],
            ['id' => 'kurzschwert', 'name' => 'Kurzschwert', 'category' => 'melee_weapons', 'summary' => 'GE, P 0, S +0', 'tw' => '3', 'bucks' => '10'],
            ['id' => 'degen', 'name' => 'Degen', 'category' => 'melee_weapons', 'summary' => 'GE, P 0, S +1', 'tw' => '10', 'bucks' => '40'],
            ['id' => 'entermesser-saebel', 'name' => 'Entermesser / Säbel', 'category' => 'melee_weapons', 'summary' => 'GE, P 0, S +1', 'tw' => '8', 'bucks' => '35'],
            ['id' => 'schwert', 'name' => 'Schwert', 'category' => 'melee_weapons', 'summary' => 'ST/GE, P 0, S +1', 'tw' => '14', 'bucks' => '100'],
            ['id' => 'axt', 'name' => 'Axt', 'category' => 'melee_weapons', 'summary' => 'ST, P 0, S +1, Wurf E, RI 3m, MR 15m', 'tw' => '16', 'bucks' => '50'],
            ['id' => 'keule', 'name' => 'Keule', 'category' => 'melee_weapons', 'summary' => 'ST, P 0, S +1, Wurf E, RI 2m, MR 10m', 'tw' => '4', 'bucks' => '5'],
            ['id' => 'baseballschlaeger', 'name' => 'Baseballschläger', 'category' => 'melee_weapons', 'summary' => 'ST, P 0, S +1', 'tw' => '4', 'bucks' => '20'],
            ['id' => 'schockstab-hydrit', 'name' => 'Schockstab (Hydrit)', 'category' => 'melee_weapons', 'summary' => 'GE, P 0, S +1', 'tw' => '120', 'bucks' => '1K', 'requiresHighTechAdvantage' => true],
            ['id' => 'harpoon-speer', 'name' => 'Harpoon / Speer (2H)', 'category' => 'melee_weapons', 'summary' => 'ST, P 0, S +2, Wurf E, RI 5m, MR 25m', 'tw' => '12', 'bucks' => '15'],
            ['id' => 'lanze-hellebarde', 'name' => 'Lanze / Hellebarde (2H)', 'category' => 'melee_weapons', 'summary' => 'ST, P 0, S +2', 'tw' => '12', 'bucks' => '75'],
            ['id' => 'kampfstab', 'name' => 'Kampfstab (2H)', 'category' => 'melee_weapons', 'summary' => 'ST/GE, P 0, S +2', 'tw' => '6', 'bucks' => '5'],
            ['id' => 'zweihandschwert', 'name' => 'Zweihandschwert (2H)', 'category' => 'melee_weapons', 'summary' => 'ST, P 0, S +2', 'tw' => '27', 'bucks' => '350'],
            ['id' => 'streitaxt', 'name' => 'Streitaxt (2H)', 'category' => 'melee_weapons', 'summary' => 'ST, P 0, S +2', 'tw' => '24', 'bucks' => '120'],
            ['id' => 'kettensaege', 'name' => 'Kettensäge (2H)', 'category' => 'melee_weapons', 'summary' => 'ST, P 0, S +3; Techno-Gegenstand (MB 1), Sprit für W66 Kampfrunden', 'tw' => '72', 'bucks' => '800', 'requiresHighTechAdvantage' => true, 'minimumEducation' => 1],

            ['id' => 'geworfener-stein', 'name' => 'Geworfener Stein', 'category' => 'ranged_weapons', 'summary' => 'S -2, P +0, Wurfgeschoss, FR E, RI 6m, MR 30m', 'tw' => '-', 'bucks' => '-', 'ammunition' => ['amount' => 30, 'unit' => 'Steine']],
            ['id' => 'schleuder', 'name' => 'Schleuder', 'category' => 'ranged_weapons', 'summary' => 'S -1, P +0, Projektilwaffe, FR E, RI 10m, MR 50m', 'tw' => '3', 'bucks' => '2', 'ammunition' => ['amount' => 30, 'unit' => 'Steine']],
            ['id' => 'zwille', 'name' => 'Zwille', 'category' => 'ranged_weapons', 'summary' => 'S +0, P +0, Projektilwaffe, FR E, RI 8m, MR 40m', 'tw' => '4', 'bucks' => '5', 'ammunition' => ['amount' => 30, 'unit' => 'Steine']],
            ['id' => 'bogen', 'name' => 'Bogen', 'category' => 'ranged_weapons', 'summary' => 'S -1, P +1, Projektilwaffe, FR E, RI 15m, MR 75m', 'tw' => '12', 'bucks' => '30', 'ammunition' => ['amount' => 30, 'unit' => 'Pfeile']],
            ['id' => 'armbrust', 'name' => 'Armbrust', 'category' => 'ranged_weapons', 'summary' => 'P +0, S +1, Projektilwaffe, FR E, RI 20m, MR 100m, M 1', 'tw' => '16', 'bucks' => '40', 'ammunition' => ['amount' => 30, 'unit' => 'Bolzen']],
            ['id' => 'revolver', 'name' => 'Revolver', 'category' => 'ranged_weapons', 'summary' => 'P +1, S +2, Schießpulverwaffe, FR E, RI 4m, MR 40m, M 6; Techno-Gegenstand (MB 2)', 'tw' => '135', 'bucks' => '350', 'requiresHighTechAdvantage' => true, 'minimumEducation' => 2, 'ammunition' => ['amount' => 4, 'unit' => 'Magazine']],
            ['id' => 'automatikpistole', 'name' => 'Automatikpistole', 'category' => 'ranged_weapons', 'summary' => 'P +1, S +2, Schießpulverwaffe, FR H, RI 6m, MR 60m, M 20; Techno-Gegenstand (MB 2)', 'tw' => '300', 'bucks' => '600', 'requiresHighTechAdvantage' => true, 'minimumEducation' => 2, 'ammunition' => ['amount' => 4, 'unit' => 'Magazine']],
            ['id' => 'gewehr-bolzer', 'name' => 'Gewehr / Bolzer', 'category' => 'ranged_weapons', 'summary' => 'P +1, S +2, Schießpulverwaffe, FR H, RI 60m, MR 600m, M 6; Techno-Gegenstand (MB 2)', 'tw' => '300', 'bucks' => '1K', 'requiresHighTechAdvantage' => true, 'minimumEducation' => 2, 'ammunition' => ['amount' => 4, 'unit' => 'Magazine']],
            ['id' => 'maschinenpistole', 'name' => 'Maschinenpistole', 'category' => 'ranged_weapons', 'summary' => 'P +1, S +2, Schießpulverwaffe, FR A, RI 25m, MR 250m, M 40; Techno-Gegenstand (MB 2)', 'tw' => '600', 'bucks' => '5K', 'requiresHighTechAdvantage' => true, 'minimumEducation' => 2, 'ammunition' => ['amount' => 4, 'unit' => 'Magazine']],
            ['id' => 'automatikgewehr', 'name' => 'Automatikgewehr', 'category' => 'ranged_weapons', 'summary' => 'P +1, S +2, Schießpulverwaffe, FR A, RI 50m, MR 500m, M 40; Techno-Gegenstand (MB 2)', 'tw' => '1,2K', 'bucks' => '7K', 'requiresHighTechAdvantage' => true, 'minimumEducation' => 2, 'ammunition' => ['amount' => 4, 'unit' => 'Magazine']],
            ['id' => 'driller', 'name' => 'Driller', 'category' => 'ranged_weapons', 'summary' => 'P +0, S +3, Energiewaffe, FR E, RI 8m, MR 80m, M 50; Techno-Gegenstand (MB 3)', 'tw' => '4K', 'bucks' => '25K', 'requiresHighTechAdvantage' => true, 'minimumEducation' => 3, 'ammunition' => ['amount' => 4, 'unit' => 'Magazine']],
            ['id' => 'energiegewehr', 'name' => 'Energiegewehr', 'category' => 'ranged_weapons', 'summary' => 'P +2, S +3, Energiewaffe, FR H, RI 80m, MR 800m, M bis 100; Techno-Gegenstand (MB 3)', 'tw' => '10K', 'bucks' => '50K', 'requiresHighTechAdvantage' => true, 'minimumEducation' => 3, 'ammunition' => ['amount' => 4, 'unit' => 'Energiezellen']],
            ['id' => 'kanone', 'name' => 'Kanone', 'category' => 'ranged_weapons', 'summary' => 'P +0, S +3, Schießpulverwaffe, FR E, RI 60m, MR 600m, M 1', 'tw' => '208', 'bucks' => '5K', 'ammunition' => ['amount' => 4, 'unit' => 'Magazine']],

            ['id' => 'felle', 'name' => 'Felle', 'category' => 'armor', 'summary' => 'SF 0, BM 0', 'tw' => '6', 'bucks' => '25'],
            ['id' => 'lederruestung', 'name' => 'Lederrüstung', 'category' => 'armor', 'summary' => 'SF 0, BM 0', 'tw' => '9', 'bucks' => '50'],
            ['id' => 'verstaerktes-leder', 'name' => 'Verstärktes Leder', 'category' => 'armor', 'summary' => 'SF 1, BM -1', 'tw' => '16', 'bucks' => '75'],
            ['id' => 'kettenhemd', 'name' => 'Kettenhemd', 'category' => 'armor', 'summary' => 'SF 1, BM -1', 'tw' => '28', 'bucks' => '250'],
            ['id' => 'andronenpanzer', 'name' => 'Andronenpanzer', 'category' => 'armor', 'summary' => 'SF 2, BM -2', 'tw' => '14', 'bucks' => '200'],
            ['id' => 'schutzanzug', 'name' => 'Schutzanzug', 'category' => 'armor', 'summary' => 'SF 1, BM 0; Techno-Gegenstand (MB 3)', 'tw' => '60', 'bucks' => '2K', 'requiresHighTechAdvantage' => true, 'minimumEducation' => 3],
            ['id' => 'kampfpanzer', 'name' => 'Kampfpanzer', 'category' => 'armor', 'summary' => 'SF 3, BM -1; Techno-Gegenstand (MB 3)', 'tw' => '590', 'bucks' => '20K', 'requiresHighTechAdvantage' => true, 'minimumEducation' => 3],

            ['id' => 'holzschild', 'name' => 'Holzschild', 'category' => 'shields', 'summary' => 'Schild', 'tw' => '6', 'bucks' => '30'],
            ['id' => 'metallschild', 'name' => 'Metallschild', 'category' => 'shields', 'summary' => 'Schild', 'tw' => '9', 'bucks' => '50'],
            ['id' => 'plastikschild', 'name' => 'Plastikschild', 'category' => 'shields', 'summary' => 'Schild', 'tw' => '12', 'bucks' => '70'],

            ['id' => 'decke', 'name' => 'Decke', 'category' => 'low_tech', 'summary' => 'Um sich bei schlechtem Wetter warm zu halten', 'tw' => '2', 'bucks' => '5'],
            ['id' => 'dietriche', 'name' => 'Dietriche', 'category' => 'low_tech', 'summary' => 'Für Diebe zum Öffnen von Schlössern', 'tw' => '6', 'bucks' => '50'],
            ['id' => 'fackel', 'name' => 'Fackel', 'category' => 'low_tech', 'summary' => 'Brennt 4 Stunden', 'tw' => '1', 'bucks' => '1'],
            ['id' => 'guerteltasche', 'name' => 'Gürteltasche', 'category' => 'low_tech', 'summary' => 'Trägt bis zu 300g Inhalt', 'tw' => '1', 'bucks' => '1'],
            ['id' => 'kreide', 'name' => 'Kreide', 'category' => 'low_tech', 'summary' => 'Um Markierungen vorzunehmen', 'tw' => '1', 'bucks' => '1'],
            ['id' => 'papier-10-blatt', 'name' => 'Papier (10 Blatt)', 'category' => 'low_tech', 'summary' => 'Um Aufzeichnungen zu machen', 'tw' => '1', 'bucks' => '5'],
            ['id' => 'rucksack', 'name' => 'Rucksack', 'category' => 'low_tech', 'summary' => 'Trägt bis zu 10 Kilogramm Inhalt', 'tw' => '3', 'bucks' => '10'],
            ['id' => 'sack', 'name' => 'Sack', 'category' => 'low_tech', 'summary' => 'Trägt bis zu 20 Kilogramm Inhalt', 'tw' => '2', 'bucks' => '4'],
            ['id' => 'schlafsack', 'name' => 'Schlafsack', 'category' => 'low_tech', 'summary' => 'Um sich bei schlechtem Wetter warm zu halten', 'tw' => '3', 'bucks' => '12'],
            ['id' => 'schreibfeder', 'name' => 'Schreibfeder', 'category' => 'low_tech', 'summary' => 'Um mit Tinte zu schreiben', 'tw' => '1', 'bucks' => '8'],
            ['id' => 'seil', 'name' => 'Seil', 'category' => 'low_tech', 'summary' => '20 Meter langes Hanfseil', 'tw' => '2', 'bucks' => '6'],
            ['id' => 'spiegel-klein', 'name' => 'Spiegel (klein)', 'category' => 'low_tech', 'summary' => 'Für Signale, um die Ecken zu schauen und zum Rasieren', 'tw' => '12', 'bucks' => '25'],
            ['id' => 'tintenfass', 'name' => 'Tintenfass', 'category' => 'low_tech', 'summary' => 'Genug Tinte, um 50 Blatt zu beschreiben', 'tw' => '4', 'bucks' => '30'],
            ['id' => 'verbandskasten', 'name' => 'Verbandskasten', 'category' => 'low_tech', 'summary' => 'Um medizinische Tätigkeiten ohne Abzug auszuüben', 'tw' => '14', 'bucks' => '100'],
            ['id' => 'wasserschlauch', 'name' => 'Wasserschlauch', 'category' => 'low_tech', 'summary' => 'Enthält 8 Liter Wasser', 'tw' => '2', 'bucks' => '2'],
            ['id' => 'weinschlauch', 'name' => 'Weinschlauch', 'category' => 'low_tech', 'summary' => 'Enthält 4 Liter Wein', 'tw' => '4', 'bucks' => '8'],
            ['id' => 'wochenration', 'name' => 'Wochenration', 'category' => 'low_tech', 'summary' => 'Getrocknete Lebensmittel für 7 Tage', 'tw' => '4', 'bucks' => '15'],
            ['id' => 'wurfanker', 'name' => 'Wurfanker', 'category' => 'low_tech', 'summary' => 'Um mit einem Seil Mauern zu überwinden', 'tw' => '8', 'bucks' => '25'],
            ['id' => 'zunderbeutel', 'name' => 'Zunderbeutel', 'category' => 'low_tech', 'summary' => 'Erlaubt es, in 1W6 Minuten Feuer zu machen', 'tw' => '2', 'bucks' => '3'],

            ['id' => 'autonomer-trilithium-computer', 'name' => 'Autonomer Trilithium Computer (ATC)', 'category' => 'high_tech', 'summary' => 'Verbindung zu einer Zentral-Helix in englischen Communities über geringe Reichweite', 'tw' => '100', 'bucks' => '2K', 'requiresHighTechAdvantage' => true],
            ['id' => 'atemgeraet', 'name' => 'Atemgerät', 'category' => 'high_tech', 'summary' => 'Erlaubt das Atmen ohne Sauerstoff für 4 Stunden', 'tw' => '90', 'bucks' => '300', 'requiresHighTechAdvantage' => true],
            ['id' => 'bionocular', 'name' => 'Bionocular', 'category' => 'high_tech', 'summary' => 'Britische Weiterentwicklung von Ferngläsern, bis zu 100fache stufenlose Vergrößerung', 'tw' => '140', 'bucks' => '800', 'requiresHighTechAdvantage' => true],
            ['id' => 'entfernungsmesser', 'name' => 'Entfernungsmesser', 'category' => 'high_tech', 'summary' => 'Misst Entfernungen bis zu 1000 Meter zentimetergenau', 'tw' => '35', 'bucks' => '100', 'requiresHighTechAdvantage' => true],
            ['id' => 'fernglas', 'name' => 'Fernglas', 'category' => 'high_tech', 'summary' => '20fache stufenlose Vergrößerung inklusive Nachtsicht', 'tw' => '70', 'bucks' => '400', 'requiresHighTechAdvantage' => true],
            ['id' => 'funkgeraet', 'name' => 'Funkgerät', 'category' => 'high_tech', 'summary' => 'Tragbares Funkgerät (Reichweite: 200m)', 'tw' => '35', 'bucks' => '600', 'requiresHighTechAdvantage' => true],
            ['id' => 'gasmaske', 'name' => 'Gasmaske', 'category' => 'high_tech', 'summary' => 'Schützt für 4 Stunden vor giftigen Dämpfen / Gasen', 'tw' => '90', 'bucks' => '250', 'requiresHighTechAdvantage' => true],
            ['id' => 'gegengift', 'name' => 'Gegengift', 'category' => 'high_tech', 'summary' => '6 Tabletten, die Gifte neutralisieren', 'tw' => '175', 'bucks' => '100', 'requiresHighTechAdvantage' => true],
            ['id' => 'heilgel', 'name' => 'Heilgel', 'category' => 'high_tech', 'summary' => 'Reduziert Verwundungen um maximal eine Stufe', 'tw' => '175', 'bucks' => '300', 'requiresHighTechAdvantage' => true],
            ['id' => 'iss-funkgeraet', 'name' => 'ISS-Funkgerät', 'category' => 'high_tech', 'summary' => 'Weltrat-Funkgerät für weltweite Kommunikation über Relaisstation auf der ISS', 'tw' => '100', 'bucks' => '100K', 'requiresHighTechAdvantage' => true],
            ['id' => 'kommunikator', 'name' => 'Kommunikator', 'category' => 'high_tech', 'summary' => 'Headset für Funkkommunikation (Reichweite: 100m)', 'tw' => '70', 'bucks' => '950', 'requiresHighTechAdvantage' => true],
            ['id' => 'laserpointer', 'name' => 'Laserpointer', 'category' => 'high_tech', 'summary' => 'Um auf Dinge in bis zu 20m Entfernung zu zeigen', 'tw' => '20', 'bucks' => '75', 'requiresHighTechAdvantage' => true],
            ['id' => 'msc', 'name' => 'MSC (Memory Storage Crystal)', 'category' => 'high_tech', 'summary' => 'Nachfolger der DVD, bis zu 800 Terabyte Daten; Abspielgerät benötigt', 'tw' => '5-10', 'bucks' => '5K', 'requiresHighTechAdvantage' => true],
            ['id' => 'nachtsichtbrille', 'name' => 'Nachtsichtbrille', 'category' => 'high_tech', 'summary' => 'Brille, die es erlaubt, im Dunkeln zu sehen', 'tw' => '350', 'bucks' => '3K', 'requiresHighTechAdvantage' => true],
            ['id' => 'plastiksprengstoff', 'name' => 'Plastiksprengstoff', 'category' => 'high_tech', 'summary' => '100g; Flächenschaden 0/+2/10m bei elektrischem Impuls', 'tw' => '105', 'bucks' => '500', 'requiresHighTechAdvantage' => true],
            ['id' => 'serumsbeutel', 'name' => 'Serumsbeutel', 'category' => 'high_tech', 'summary' => 'Erlaubt trotz tödlicher Immunschwäche (5 + RO) Tage ohne Schutzanzug', 'tw' => '7-30', 'bucks' => '300', 'requiresHighTechAdvantage' => true],
            ['id' => 'uebersetzungscomputer-neuronal', 'name' => 'Übersetzungscomputer (Neuronal)', 'category' => 'high_tech', 'summary' => 'Übersetzt bekannte Sprachen und entschlüsselt unbekannte selbstständig', 'tw' => '350', 'bucks' => '20K', 'requiresHighTechAdvantage' => true],
            ['id' => 'trilithium-rechner', 'name' => 'Trilithium-Rechner', 'category' => 'high_tech', 'summary' => 'Ca. 5cm großer Computer der englischen Communities für Kommunikation und Datenaustausch', 'tw' => '45', 'bucks' => '4K', 'requiresHighTechAdvantage' => true],
            ['id' => 'zeitzuender', 'name' => 'Zeitzünder', 'category' => 'high_tech', 'summary' => 'Für Plastiksprengstoff; Verzögerung von 10 Sekunden bis 24 Stunden', 'tw' => '20', 'bucks' => '25', 'requiresHighTechAdvantage' => true],

            ['id' => 'androne', 'name' => 'Androne', 'category' => 'transport', 'summary' => 'Reittier/Fortbewegungsmittel; P 1, SW 2, G 0/-/30', 'tw' => '200', 'bucks' => '1200'],
            ['id' => 'frekkeuscher', 'name' => 'Frekkeuscher', 'category' => 'transport', 'summary' => 'Reittier/Fortbewegungsmittel; P 1, SW 2, G 0/-/25', 'tw' => '160', 'bucks' => '800'],
            ['id' => 'eissegler-klein', 'name' => 'Eissegler (klein)', 'category' => 'transport', 'summary' => 'P 1, SW 1, G -/*/50', 'tw' => '1K', 'bucks' => '5K'],
            ['id' => 'eissegler-gross', 'name' => 'Eissegler (groß)', 'category' => 'transport', 'summary' => 'P 2-4, SW 2, G -/*/40, MB 1', 'tw' => '3K', 'bucks' => '15K', 'minimumEducation' => 1],
            ['id' => 'ewat', 'name' => 'EWAT (Earth Water Air Tank)', 'category' => 'transport', 'summary' => 'P 16, SW 8, G 90/60/80, MB 3', 'tw' => '30K', 'bucks' => '2000K', 'requiresHighTechAdvantage' => true, 'minimumEducation' => 3],
            ['id' => 'grashopper', 'name' => 'Grashopper (General All Purpose 4x4 Ground)', 'category' => 'transport', 'summary' => 'P 6, SW 4, G 60, MB 3', 'tw' => '5K', 'bucks' => '200K', 'requiresHighTechAdvantage' => true, 'minimumEducation' => 3],
            ['id' => 'motorrad', 'name' => 'Motorrad', 'category' => 'transport', 'summary' => 'P 1-2, SW 0, G -/-/120, MB 1', 'tw' => '3K', 'bucks' => '12K', 'minimumEducation' => 1],
            ['id' => 'nixon', 'name' => 'Nixon (WCA-Kettenpanzer)', 'category' => 'transport', 'summary' => 'P 2-6, SW 6, G -/-/80, MB 3', 'tw' => '20K', 'bucks' => '750K', 'requiresHighTechAdvantage' => true, 'minimumEducation' => 3],
            ['id' => 'segelschiff', 'name' => 'Segelschiff', 'category' => 'transport', 'summary' => 'P 2-80, SW 0, G 2/-/20, MB 2', 'tw' => '10K', 'bucks' => '80K', 'minimumEducation' => 2],
            ['id' => 'transportqualle', 'name' => 'Transportqualle', 'category' => 'transport', 'summary' => 'P 1, SW 10, G 3/-/-/20, MB 3', 'tw' => '-', 'bucks' => '-', 'requiresHighTechAdvantage' => true, 'minimumEducation' => 3],
            ['id' => 'x-quad', 'name' => 'X-Quad', 'category' => 'transport', 'summary' => 'P 1-2, SW 1, G -/-/*/100/60, MB 2', 'tw' => '5K', 'bucks' => '25K', 'minimumEducation' => 2],
            ['id' => 'yakk', 'name' => 'Yakk', 'category' => 'transport', 'summary' => 'Reittier/Fortbewegungsmittel; P 1-3, SW 0, G -/-/8', 'tw' => '100', 'bucks' => '500'],
        ];
    }
}
