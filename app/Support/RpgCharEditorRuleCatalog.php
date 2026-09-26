<?php

namespace App\Support;

final class RpgCharEditorRuleCatalog
{
    public const BASE = 'base';

    public const EXPANSION = 'expansion-1';

    public const MARTIAN_CULTURE = 'Marsianische Städter';

    public const MARTIAN_BONUS_SKILLS = ['Pilot', 'Wissenschaftler', 'Athletik', 'Unterhalten'];

    public const HUMAN_POOL = ['Bildung', 'Fahren', 'Feuerwaffen', 'Pilot', 'Techniker', 'Wissenschaftler'];

    public const POOL_POINTS = 12;

    public static function sources(): array
    {
        return [
            self::BASE => ['id' => self::BASE, 'name' => 'Basisregelwerk', 'author' => null, 'version' => '2007', 'defaultEnabled' => true],
            self::EXPANSION => ['id' => self::EXPANSION, 'name' => '1. Erweiterung von Stefan Küppers', 'author' => 'Stefan Küppers', 'version' => '1', 'defaultEnabled' => true],
        ];
    }

    public static function snapshots(array $selection): array
    {
        $result = [];
        foreach (self::sources() as $id => $source) {
            if ($id === self::BASE || ($selection[$id] ?? false)) {
                unset($source['defaultEnabled']);
                $result[] = $source;
            }
        }

        return $result;
    }

    public static function races(): array
    {
        $definitions = [
            'Barbar' => ['skills' => ['Überleben' => 1, 'Intuition' => 1], 'anySkills' => [['names' => ['Nahkampf', 'Fernkampf'], 'minimum' => 1, 'label' => 'Nahkampf oder Fernkampf mindestens auf 1']]],
            'Guul' => ['attributes' => ['au' => -1], 'skills' => ['Heimlichkeit' => 2, 'Intuition' => 1], 'advantages' => ['Natürliche Waffen'], 'disadvantages' => ['Primitiv', 'Gejagt']],
            'Hydrit' => ['skills' => ['Athletik' => 2, 'Bildung' => 1], 'advantages' => ['Kiemen', 'Natürliche Waffen'], 'disadvantages' => ['Anfälligkeit gegen Wahnsinn'], 'culture' => 'Meeresbewohner'],
            'Nosfera' => ['attributes' => ['ge' => 1, 'au' => -1], 'skills' => ['Intuition' => 2, 'Heimlichkeit' => 2], 'advantages' => ['Nachtsicht'], 'disadvantages' => ['Blutdurst', 'Lichtscheu', 'Gejagt']],
            'Taratze' => ['attributes' => ['st' => 1, 'wa' => 1, 'in' => -1, 'au' => -1], 'skills' => ['Intuition' => 2, 'Heimlichkeit' => 1, 'Überleben' => 1], 'disadvantages' => ['Auffällig', 'Primitiv', 'Gejagt']],
            'Wulfane' => ['attributes' => ['ro' => 1, 'au' => -1], 'skills' => ['Intuition' => 1, 'Nahkampf' => 1], 'disadvantages' => ['Ehrenkodex']],
            'Techno' => ['attributes' => ['st' => -1, 'ro' => -1, 'in' => 1], 'skills' => ['Bildung' => 3], 'advantages' => ['High-Tech-Ausrüstung'], 'disadvantages' => ['Tödliche Immunschwäche'], 'culture' => 'Bunkermensch'],
            'Präkristofluu' => ['skills' => ['Beruf' => 3], 'advantages' => ['High-Tech-Ausrüstung'], 'culture' => 'Mensch des 21. Jahrhunderts', 'pool' => self::HUMAN_POOL],
            'Agarther' => [
                'source' => self::EXPANSION, 'skills' => ['Beruf' => 3], 'advantages' => ['High-Tech-Ausrüstung'], 'culture' => 'Mensch des 21. Jahrhunderts',
                'pool' => ['Bildung', 'Fahren', 'Nahkampf', 'Pilot', 'Techniker', 'Wissenschaftler'],
                'description' => 'Agarther erkunden die Welt außerhalb Agarthas häufig unter einer Tarnidentität. Sie sammeln Informationen und suchen nach verbliebenen Zentren der Zivilisation.',
                'note' => '12 Rassenpunkte wie bei Präkristofluu; Nahkampf ersetzt Feuerwaffen. Die Kulturboni entsprechen Mensch des 21. Jahrhunderts.',
            ],
            'Marsianer' => [
                'source' => self::EXPANSION, 'skills' => ['Beruf' => 3], 'advantages' => ['High-Tech-Ausrüstung'], 'culture' => self::MARTIAN_CULTURE,
                'pool' => ['Bildung', 'Fahren', 'Pilot', 'Techniker', 'Wissenschaftler'],
                'description' => 'Marsianer sind große, schlanke Menschen mit feingliedrigem Körperbau, vergrößertem Brustkorb und einer individuell gemaserten Haut. Ihre Gesellschaft hat sich über Jahrhunderte auf dem Mars entwickelt.',
                'note' => '12 Rassenpunkte wie bei Präkristofluu; eine andere Fertigkeit ersetzt Feuerwaffen. Eine geringere Robustheit auf der Erde wird empfohlen, ist aber kein automatischer Abzug.',
            ],
            'Morlock' => [
                'source' => self::EXPANSION, 'attributes' => ['in' => -1], 'skills' => ['Heimlichkeit' => 1, 'Überleben' => 1], 'advantages' => ['Nachtsicht'], 'disadvantages' => ['Lichtscheu'], 'culture' => 'Ruinenbewohner',
                'description' => 'Morlocks haben bleiche Haut, weißes Haar und sehr lichtempfindliche Augen. Sie können im Dunkeln sehen und leben in vor Tageslicht abgeschirmten Behausungen.',
            ],
        ];

        foreach ($definitions as $name => &$definition) {
            $definition += ['name' => $name, 'source' => self::BASE, 'attributes' => [], 'skills' => [], 'advantages' => [], 'disadvantages' => []];
        }

        return $definitions;
    }

    public static function cultures(): array
    {
        $definitions = [];
        foreach (['Landbewohner', 'Stadtbewohner', 'Meeresbewohner', 'Bunkermensch', 'Mensch des 21. Jahrhunderts', 'Nomade', 'Disuuslachter (Nordmann)', 'Ruinenbewohner', 'Untergrundbewohner', 'Volk der 13 Inseln'] as $name) {
            $definitions[$name] = ['name' => $name, 'source' => self::BASE];
        }
        $definitions[self::MARTIAN_CULTURE] = [
            'name' => self::MARTIAN_CULTURE, 'source' => self::EXPANSION,
            'skills' => ['Bildung' => 1, 'Techniker' => 1], 'bonusSkills' => self::MARTIAN_BONUS_SKILLS,
            'description' => 'Die marsianischen Städter haben über Jahrhunderte eine eigenständige, technisch fortgeschrittene Gesellschaft entwickelt. Gemeinschaftliche Erziehung, praktische Berufsausbildung und das Zusammenleben in großen Familienverbänden prägen ihren Alltag. Neben ihrer Arbeit widmen sich viele Marsianer sportlichen oder künstlerischen Interessen.',
        ];

        return $definitions;
    }

    public static function allowedCultures(string $race): array
    {
        $forced = self::races()[$race]['culture'] ?? null;
        if ($forced !== null) {
            return [$forced];
        }

        return array_values(array_diff(array_keys(self::cultures()), [
            'Meeresbewohner', 'Bunkermensch', 'Mensch des 21. Jahrhunderts', self::MARTIAN_CULTURE,
            ...($race === 'Barbar' ? [] : ['Volk der 13 Inseln', 'Disuuslachter (Nordmann)']),
        ]));
    }

    public static function humanPool(string $race, string $replacement = ''): array
    {
        $pool = self::races()[$race]['pool'] ?? self::HUMAN_POOL;

        return $race === 'Marsianer' && $replacement !== '' ? array_values(array_unique([...$pool, $replacement])) : $pool;
    }

    public static function ruleConfig(): array
    {
        $races = self::races();
        foreach ($races as $name => &$race) {
            $race['allowedCultures'] = self::allowedCultures($name);
        }

        return ['sources' => self::sources(), 'races' => $races, 'cultures' => self::cultures(), 'poolPoints' => self::POOL_POINTS];
    }
}
