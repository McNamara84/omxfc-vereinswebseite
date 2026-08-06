<?php

namespace App\Support;

final class RpgCharEditorTraining
{
    public const MAX_TRAININGS = 10;

    public const MAX_ALLOCATIONS = 64;

    /**
     * @return array<string, array{
     *     name: string,
     *     cost: int,
     *     skills: list<string>,
     *     description: string,
     *     requiredAdvantages?: list<string>,
     *     suggestedSpecializations?: array<string, string>
     * }>
     */
    public static function definitions(): array
    {
        return [
            'Arbeiter' => [
                'name' => 'Arbeiter',
                'cost' => 5,
                'skills' => ['Beruf', 'Fahren', 'Unterhalten'],
                'description' => 'Ein einfacher Beruf in der postapokalyptischen Zivilisation.',
            ],
            'Arzt (Heilkundiger)' => [
                'name' => 'Arzt (Heilkundiger)',
                'cost' => 5,
                'skills' => ['Beruf', 'Heiler', 'Handeln'],
                'description' => 'Ausbildung in Heilkunst, einem passenden Beruf und im Umgang mit Patienten oder Auftraggebern.',
            ],
            'Dieb' => [
                'name' => 'Dieb',
                'cost' => 6,
                'skills' => ['Diebeskunst', 'Heimlichkeit', 'Nahkampf', 'Fernkampf'],
                'description' => 'Ausbildung für Taschendiebe, Einbrecher, Räuber, Piraten oder Meuchelmörder.',
            ],
            'Forscher' => [
                'name' => 'Forscher',
                'cost' => 6,
                'skills' => ['Fahren', 'Kunde', 'Pilot', 'Reiten', 'Techniker', 'Wissenschaftler'],
                'description' => 'Erkundet die Umwelt und erschließt unbekannte Orte und Zusammenhänge.',
            ],
            'Händler' => [
                'name' => 'Händler',
                'cost' => 5,
                'skills' => ['Fahren', 'Handeln', 'Kunde', 'Reiten'],
                'description' => 'Handelt mit Waren, kennt Preise und kommt weit herum.',
            ],
            'Krieger' => [
                'name' => 'Krieger',
                'cost' => 5,
                'skills' => ['Nahkampf', 'Fernkampf', 'Feuerwaffen', 'Reiten', 'Fahren', 'Pilot'],
                'description' => 'Kampfausbildung für primitive Krieger ebenso wie für moderne Eliteeinheiten.',
            ],
            "Rev'rend" => [
                'name' => "Rev'rend",
                'cost' => 15,
                'skills' => ['Nahkampf', 'Fernkampf', 'Feuerwaffen', 'Athletik', 'Reiten', 'Kunde', 'Unterhalten'],
                'description' => 'Furchtloser Prediger und Mutantenjäger mit umfassender Kampf- und Reiseausbildung.',
                'suggestedSpecializations' => ['Unterhalten' => 'Predigen'],
            ],
            'Schamane (Göttersprecher)' => [
                'name' => 'Schamane (Göttersprecher)',
                'cost' => 5,
                'skills' => ['Kunde', 'Unterhalten', 'Nahkampf', 'Fernkampf', 'Heiler'],
                'description' => 'Spiritueller Mittler zwischen einer Gemeinschaft und höheren Wesen.',
            ],
            'Seher' => [
                'name' => 'Seher',
                'cost' => 5,
                'skills' => ['Kunde', 'Unterhalten', 'Sprachen'],
                'description' => 'Mental begabter Charakter, der Gefahren oder fremde Gedanken erspüren kann.',
                'requiredAdvantages' => ['Psychische Kraft'],
            ],
            'Truveer' => [
                'name' => 'Truveer',
                'cost' => 6,
                'skills' => ['Unterhalten', 'Kunde', 'Heimlichkeit', 'Nahkampf', 'Fernkampf'],
                'description' => 'Wandernder Sänger und Nachrichtenüberbringer der dunklen Zukunft.',
            ],
        ];
    }

    /**
     * @return array{maxTrainings: int, maxAllocations: int, trainings: list<array<string, mixed>>}
     */
    public static function ruleConfig(): array
    {
        return [
            'maxTrainings' => self::MAX_TRAININGS,
            'maxAllocations' => self::MAX_ALLOCATIONS,
            'trainings' => array_values(self::definitions()),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function map(): array
    {
        return self::definitions();
    }
}
