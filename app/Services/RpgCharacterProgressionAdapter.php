<?php

namespace App\Services;

use App\Support\RpgCharEditorRuleCatalog;
use App\Support\RpgCharEditorSpecialRules;
use Illuminate\Validation\ValidationException;

final class RpgCharacterProgressionAdapter
{
    public function normalize(array $payload): array
    {
        $names = new RpgCharacterSheetService;
        foreach (['advantages', 'disadvantages'] as $key) {
            $payload[$key] = array_values(array_unique(array_map($names->canonicalSpecialName(...), $payload[$key] ?? [])));
        }
        foreach (['advantage_counts', 'advantage_details', 'disadvantage_details'] as $key) {
            $normalized = [];
            foreach ($payload[$key] ?? [] as $name => $value) {
                $normalized[$names->canonicalSpecialName($name)] = $value;
            }
            $payload[$key] = $normalized;
        }
        $payload['attributes'] = array_replace(array_fill_keys(RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS, 0), array_map('intval', $payload['attributes'] ?? []));
        $skills = [];
        foreach ($payload['skills'] ?? [] as $skill) {
            $name = $names->canonicalSkillName($skill['name']);
            $skills[$name] = max($skills[$name] ?? 0, (int) $skill['value']);
        }
        $payload['skills'] = [];
        foreach ($skills as $name => $value) {
            $payload['skills'][] = compact('name', 'value');
        }
        $effects = [];
        foreach ($payload['advantage_effects'] ?? [] as $effect) {
            $effect['name'] = $names->canonicalSpecialName($effect['name']);
            $effects[] = $effect;
        }
        foreach ($payload['advantages'] as $name) {
            $count = count(array_filter($effects, fn (array $effect): bool => $effect['name'] === $name));
            $expected = max(1, (int) ($payload['advantage_counts'][$name] ?? 1), $count);
            while ($count++ < $expected) {
                $effects[] = ['name' => $name, 'target' => '', 'justification' => $payload['advantage_details'][$name] ?? 'Bestandscharakter'];
            }
            $payload['advantage_counts'][$name] = $expected;
        }
        $payload['advantage_effects'] = $effects;

        return $payload;
    }

    public function raceModifiers(array $payload): array
    {
        $known = $payload['progression']['race_modifiers'] ?? $payload['creation']['attribute_race_modifiers'] ?? null;
        if (is_array($known)) {
            return $known;
        }
        $race = $payload['character']['race'] ?? '';
        if ($race !== 'Barbar') {
            return RpgCharEditorRuleCatalog::races()[$race]['attributes'] ?? [];
        }
        $candidates = [];
        foreach ($payload['attributes'] as $key => $value) {
            if ($value - $this->attributeBonus($payload, $key) > 1) {
                $candidates[] = $key;
            }
        }
        if (count($candidates) === 1) {
            return [$candidates[0] => 1];
        }
        throw ValidationException::withMessages(['operations' => 'Die ursprüngliche Attributswahl dieses Barbaren muss zunächst von der AG-Leitung dokumentiert werden.']);
    }

    public function attributeBonus(array $payload, string $target): int
    {
        $bonus = 0;
        foreach ($payload['advantage_effects'] ?? [] as $effect) {
            if ($effect['name'] !== 'Gesteigertes Attribut') {
                continue;
            }
            if (($effect['target'] ?? '') === '') {
                throw ValidationException::withMessages(['operations' => 'Ein früherer Attributsvorteil hat kein dokumentiertes Ziel. Die AG-Leitung muss die Herkunft zuerst ergänzen.']);
            }
            $bonus += (int) ($effect['target'] === $target);
        }

        return $bonus;
    }
}
