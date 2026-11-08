<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\RpgCharacterSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait RpgExpansionTestFixtures
{
    use RefreshDatabase;

    private function payload(string $race = 'Morlock', string $bonus = 'Pilot', string $replacement = 'Nahkampf'): array
    {
        $adjustments = ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0];
        $attributes = $adjustments;
        $pool = [];
        if ($race === 'Morlock') {
            $attributes['in'] = -1;
            $grants = ['Heimlichkeit' => 2, 'Überleben' => 1, 'Diebeskunst' => 1, 'Nahkampf' => 1];
            $culture = 'Ruinenbewohner';
        } else {
            $culture = $race === 'Marsianer' ? 'Marsianische Städter' : 'Mensch des 21. Jahrhunderts';
            $pool = ['Bildung' => 2, 'Fahren' => 2, 'Pilot' => 2, 'Techniker' => 2, 'Wissenschaftler' => 2];
            $pool[$race === 'Agarther' ? 'Nahkampf' : $replacement] = ($pool[$replacement] ?? 0) + 2;
            $grants = ['Beruf' => 3];
            foreach ($pool as $skill => $points) {
                $grants[$skill] = ($grants[$skill] ?? 0) + $points;
            }
            foreach ($race === 'Agarther' ? ['Beruf', 'Bildung', 'Pilot'] : ['Bildung', 'Techniker', $bonus] as $skill) {
                $grants[$skill] = ($grants[$skill] ?? 0) + 1;
            }
        }
        $skills = $grants;
        $paid = 20;
        foreach (['Heiler', 'Fernkampf', 'Handeln', 'Athletik', 'Reiten', 'Unterhalten', 'Feuerwaffen'] as $skill) {
            if ($paid === 0) {
                break;
            }
            if (! isset($skills[$skill])) {
                $skills[$skill] = min(4, $paid);
                $paid -= $skills[$skill];
            }
        }
        expect($paid)->toBe(0);

        return ['figurenstaerke' => 3, 'player_name' => 'Regeltest', 'character_name' => $race.' Test', 'gender' => 'divers', 'race' => $race, 'culture' => $culture, 'rule_sources' => ['expansion-1' => '1'], 'attributes' => $attributes, 'attribute_adjustments' => $adjustments, 'skills' => array_map(static fn ($name, $value) => compact('name', 'value'), array_keys($skills), array_values($skills)), 'advantages' => $race === 'Morlock' ? ['Zäh', 'Nachtsicht'] : ['Zäh', 'High-Tech-Ausrüstung'], 'disadvantages' => $race === 'Morlock' ? ['Lichtscheu'] : [], 'praekristofluu_skill_points' => $pool, 'mensch_21_first_bonus_skill' => 'Bildung', 'mensch_21_second_bonus_skill' => 'Pilot', 'marsianer_bonus_skill' => $bonus, 'marsianer_replacement_skill' => $replacement, 'languages' => $replacement === 'Sprachen' ? ['Marsianisch', 'Englisch'] : [], 'clothing' => 'kleidung-einfach', 'equipment_items' => array_map(static fn ($id) => ['id' => $id, 'quantity' => 1], $race === 'Morlock' ? ['messer-dolch', 'seil', 'rucksack', 'wasserschlauch', 'wochenration', 'bogen'] : ['fernglas', 'funkgeraet', 'gasmaske', 'atemgeraet', 'seil', 'rucksack'])];
    }

    private function validate(array $payload): array
    {
        return app(RpgCharacterSheetService::class)->validatedPdfPayload(Request::create('/', 'POST', $payload));
    }

    private function assertRejected(array $payload, string $field): void
    {
        try {
            $this->validate($payload);
            $this->fail('Ungültiger Erweiterungscharakter wurde akzeptiert.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey($field);
        }
    }
}
