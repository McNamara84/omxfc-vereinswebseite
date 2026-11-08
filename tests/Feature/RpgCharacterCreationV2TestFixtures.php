<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\RpgCharacterSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

trait RpgCharacterCreationV2TestFixtures
{
    use RefreshDatabase;

    /** @return array<string, int> */
    private function completeAttributeAdjustmentsForLevel(int $level): array
    {
        $adjustments = ['st' => 0, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0];
        $budgets = [1 => 0, 2 => 1, 3 => 2, 4 => 3, 5 => 4];
        foreach (array_slice(array_keys($adjustments), 0, $budgets[$level]) as $attribute) {
            $adjustments[$attribute] = 1;
        }

        return $adjustments;
    }

    /**
     * Complete paid FP fixture for the default Barbar/Landbewohner selection.
     *
     * @param  list<array{name: string, value: int}>  $requestedSkills
     * @return list<array{name: string, value: int}>
     */
    private function completeSkillsForLevel(int $level, array $requestedSkills = []): array
    {
        $budgets = [1 => 10, 2 => 15, 3 => 20, 4 => 40, 5 => 60];
        $maximums = [1 => 3, 2 => 3, 3 => 4, 4 => 5, 5 => 6];
        $grants = ['Nahkampf' => 1, 'Überleben' => 1, 'Intuition' => 1, 'Beruf: Viehzüchter' => 2, 'Kunde: Wetter' => 1];
        $skills = array_map(static fn (string $name, int $value): array => ['name' => $name, 'value' => $value], array_keys($grants), array_values($grants));
        foreach ($requestedSkills as $requestedSkill) {
            $index = array_search($requestedSkill['name'], array_column($skills, 'name'), true);
            if ($index === false) {
                $skills[] = $requestedSkill;
            } else {
                $skills[$index] = $requestedSkill;
            }
        }
        $used = array_reduce($skills, static fn (int $sum, array $skill): int => $sum + max($skill['value'] - ($grants[$skill['name']] ?? 0), 0), 0);
        $remaining = $budgets[$level] - $used;
        foreach (['Athletik', 'Fernkampf', 'Handeln', 'Fahren', 'Feuerwaffen', 'Heiler', 'Pilot', 'Reiten', 'Techniker', 'Unterhalten'] as $name) {
            if ($remaining <= 0 || in_array($name, array_column($skills, 'name'), true)) {
                continue;
            }
            $points = min($maximums[$level], $remaining);
            $skills[] = ['name' => $name, 'value' => $points];
            $remaining -= $points;
        }
        if ($remaining !== 0) {
            throw new \RuntimeException("Das Test-Fixture für Figurenstärke {$level} konnte nicht vollständig verteilt werden.");
        }

        return $skills;
    }

    private function payload(array $overrides = []): array
    {
        $requestedLevel = $overrides['figurenstaerke'] ?? 3;
        $fixtureLevel = filter_var($requestedLevel, FILTER_VALIDATE_INT);
        $fixtureLevel = in_array($fixtureLevel, [1, 2, 3, 4, 5], true) ? $fixtureLevel : 3;
        $attributeAdjustments = $this->completeAttributeAdjustmentsForLevel($fixtureLevel);
        $attributes = $attributeAdjustments;
        $attributes['st']++;
        $payload = array_replace_recursive(['figurenstaerke' => 3, 'player_name' => 'Regeltest', 'character_name' => 'Niveau Drei', 'gender' => 'maennlich', 'race' => 'Barbar', 'culture' => 'Landbewohner', 'description' => '', 'barbar_attribute_bonus' => 'st', 'attribute_adjustments' => $attributeAdjustments, 'attributes' => $attributes, 'skills' => $this->completeSkillsForLevel($fixtureLevel), 'advantages' => ['Zäh'], 'disadvantages' => [], 'advantage_effects' => [], 'languages' => [], 'clothing' => 'kleidung-einfach', 'equipment_items' => [['id' => 'messer-dolch', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1], ['id' => 'wasserschlauch', 'quantity' => 1], ['id' => 'wochenration', 'quantity' => 1], ['id' => 'bogen', 'quantity' => 1]]], $overrides);
        foreach (['attribute_adjustments', 'attributes', 'skills', 'advantages', 'disadvantages', 'advantage_effects', 'languages', 'equipment_items'] as $key) {
            if (array_key_exists($key, $overrides)) {
                $payload[$key] = $overrides[$key];
            }
        }

        return $payload;
    }

    private function validate(array $payload): array
    {
        return app(RpgCharacterSheetService::class)->validatedPdfPayload(Request::create('/', 'POST', $payload));
    }
}
