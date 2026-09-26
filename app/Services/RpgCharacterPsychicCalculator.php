<?php

namespace App\Services;

final class RpgCharacterPsychicCalculator
{
    public function calculate(array $payload): array
    {
        $powers = array_values(array_unique(array_filter(array_column(array_filter(
            $payload['advantage_effects'] ?? [],
            fn (array $effect): bool => ($effect['name'] ?? '') === 'Psychische Kraft',
        ), 'target'))));
        $skills = array_column($payload['skills'] ?? [], 'value', 'name');
        $willpower = (int) ($payload['attributes']['wi'] ?? 0);
        $rows = array_map(fn (string $name): array => [
            'name' => $name,
            'value' => (int) ($skills[$name] ?? 0),
            'usable' => $willpower >= 0 && ($skills[$name] ?? 0) >= 1,
        ], $powers);
        $values = array_column($rows, 'value');
        $sum = array_sum($values);
        if (in_array('Psychisches Reservoir', $payload['advantages'] ?? [], true)) {
            $sum += $values === [] ? 0 : max($values);
        }

        return ['powers' => $rows, 'pep' => $sum * max(0, count($powers) + $willpower)];
    }
}
