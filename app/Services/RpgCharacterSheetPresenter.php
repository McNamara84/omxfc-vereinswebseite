<?php

namespace App\Services;

use App\Support\RpgCharEditorSpecialRules;
use Illuminate\Support\Str;

final class RpgCharacterSheetPresenter
{
    private const ATTRIBUTE_LABELS = [
        'st' => 'Stärke',
        'ge' => 'Geschicklichkeit',
        'ro' => 'Robustheit',
        'wi' => 'Willenskraft',
        'wa' => 'Wahrnehmung',
        'in' => 'Intelligenz',
        'au' => 'Auftreten',
    ];

    private const SKILL_COLUMNS = [
        [
            'Athletik',
            'Beruf',
            'Bildung',
            'Diebeskunst',
            'Fahren',
            'Fernkampf',
            'Feuerwaffen',
            'Handeln',
            'Heiler',
            'Heimlichkeit',
        ],
        [
            'Intuition',
            'Kunde',
            'Nahkampf',
            'Pilot',
            'Reiten',
            'Sprachen',
            'Techniker',
            'Unterhalten',
            'Überleben',
            'Wissenschaftler',
        ],
    ];

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $combat
     * @return array<string, mixed>
     */
    public function present(array $data, array $combat): array
    {
        $character = is_array($data['character'] ?? null) ? $data['character'] : [];
        $skills = is_array($data['skills'] ?? null) ? $data['skills'] : [];
        $equipment = is_array($data['equipment'] ?? null) ? $data['equipment'] : [];
        $rules = is_array($data['rules'] ?? null) ? $data['rules'] : [];
        $creationLevel = (int) ($rules['creation_level'] ?? RpgCharEditorSpecialRules::DEFAULT_CREATION_LEVEL);
        if (! in_array($creationLevel, RpgCharEditorSpecialRules::validCreationLevels(), true)) {
            $creationLevel = RpgCharEditorSpecialRules::DEFAULT_CREATION_LEVEL;
        }

        return [
            'character_name' => $this->short($character['character_name'] ?? '', 70),
            'player_name' => $this->short($character['player_name'] ?? '', 60),
            'gender' => $this->genderLabel((string) ($character['gender'] ?? '')),
            'race_culture' => $this->short(trim((string) ($character['race'] ?? '').' · '.(string) ($character['culture'] ?? ''), ' ·'), 85),
            'creation_level' => $creationLevel,
            'trainings' => $this->short($this->trainingText($data['trainings'] ?? []), 100),
            'professions' => $this->short($this->specializationText($skills, 'Beruf'), 100),
            'description' => $this->short($character['description'] ?? '', 230),
            'portrait' => is_string($data['portrait'] ?? null) ? $data['portrait'] : null,
            'attributes' => $this->attributeRows($data['attributes'] ?? []),
            'skill_columns' => $this->skillColumns($skills),
            'specializations' => $this->short($this->allSpecializationsText($skills, $data['languages'] ?? []), 240),
            'advantages' => $this->short($this->advantageText(
                $data['advantages'] ?? [],
                $data['advantage_details'] ?? [],
                $data['advantage_counts'] ?? [],
                $data['advantage_effects'] ?? [],
            ), 310),
            'disadvantages' => $this->short($this->specialText(
                $data['disadvantages'] ?? [],
                $data['disadvantage_details'] ?? [],
            ), 310),
            'combat' => $combat,
            'weapons' => $this->weaponRows(is_array($combat['weapons'] ?? null) ? $combat['weapons'] : []),
            'armor' => is_array($combat['armor'] ?? null) ? $combat['armor'] : [],
            'equipment' => $this->equipmentText($equipment),
            'ammunition' => $this->ammunitionText($equipment['ammunition'] ?? []),
            'notes' => $this->short($equipment['notes'] ?? '', 180),
        ];
    }

    private function genderLabel(string $gender): string
    {
        return match ($gender) {
            'weiblich' => 'weiblich',
            'maennlich' => 'männlich',
            'divers' => 'divers',
            default => '',
        };
    }

    private function trainingText(mixed $trainings): string
    {
        if (! is_array($trainings)) {
            return 'Abenteurer';
        }

        $names = array_values(array_filter(array_map(
            static fn (mixed $training): string => is_scalar($training) ? trim((string) $training) : '',
            $trainings,
        )));

        return $names === [] ? 'Abenteurer' : implode(', ', $names);
    }

    /**
     * @return list<array{key: string, label: string, value: string}>
     */
    private function attributeRows(mixed $attributes): array
    {
        $attributes = is_array($attributes) ? $attributes : [];
        $rows = [];

        foreach (self::ATTRIBUTE_LABELS as $key => $label) {
            $value = is_numeric($attributes[$key] ?? null) ? (int) $attributes[$key] : 0;
            $rows[] = ['key' => $key, 'label' => $label, 'value' => $this->modifier($value, false)];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $skills
     * @return list<list<array{name: string, value: int}>>
     */
    private function skillColumns(array $skills): array
    {
        return array_map(function (array $names) use ($skills): array {
            return array_map(fn (string $name): array => [
                'name' => $name,
                'value' => $this->skillValue($skills, $name),
            ], $names);
        }, self::SKILL_COLUMNS);
    }

    /**
     * @param  list<array<string, mixed>>  $skills
     */
    private function skillValue(array $skills, string $name): int
    {
        $values = [];

        foreach ($skills as $skill) {
            if (! is_array($skill) || ! is_numeric($skill['value'] ?? null)) {
                continue;
            }

            $skillName = trim((string) ($skill['name'] ?? ''));

            if ($skillName === $name || str_starts_with($skillName, $name.':')) {
                $values[] = (int) $skill['value'];
            }
        }

        return $values === [] ? 0 : max($values);
    }

    /**
     * @param  list<array<string, mixed>>  $skills
     */
    private function specializationText(array $skills, string $base): string
    {
        $names = [];

        foreach ($skills as $skill) {
            if (! is_array($skill)) {
                continue;
            }

            $name = trim((string) ($skill['name'] ?? ''));

            if (! str_starts_with($name, $base.':')) {
                continue;
            }

            $detail = trim(substr($name, strlen($base) + 1));

            if ($detail !== '') {
                $names[] = $detail.' '.(int) ($skill['value'] ?? 0);
            }
        }

        return implode(', ', array_values(array_unique($names)));
    }

    /**
     * @param  list<array<string, mixed>>  $skills
     */
    private function allSpecializationsText(array $skills, mixed $languages = []): string
    {
        $parts = [];

        foreach (['Kunde', 'Sprachen', 'Unterhalten', 'Wissenschaftler'] as $base) {
            $details = $this->specializationText($skills, $base);

            if ($details !== '') {
                $parts[] = $base.': '.$details;
            }
        }

        if (is_array($languages)) {
            $languageNames = array_values(array_unique(array_filter(array_map(
                static fn (mixed $language): string => is_scalar($language) ? trim((string) $language) : '',
                $languages,
            ))));
            if ($languageNames !== []) {
                $parts = array_values(array_filter($parts, static fn (string $part): bool => ! str_starts_with($part, 'Sprachen:')));
                $parts[] = 'Sprachen: '.implode(', ', $languageNames);
            }
        }

        return implode(' · ', $parts);
    }

    private function advantageText(mixed $names, mixed $details, mixed $counts, mixed $effects): string
    {
        if (! is_array($effects) || $effects === []) {
            return $this->specialText($names, $details, $counts);
        }

        $names = is_array($names) ? $names : [];
        $grouped = [];
        foreach ($effects as $effect) {
            if (! is_array($effect)) {
                continue;
            }
            $name = trim((string) ($effect['name'] ?? ''));
            if ($name !== '') {
                $grouped[$name][] = $effect;
            }
        }

        $parts = [];
        foreach ($names as $name) {
            if (! is_scalar($name) || trim((string) $name) === '') {
                continue;
            }
            $name = trim((string) $name);
            $instances = $grouped[$name] ?? [];
            $instanceParts = [];
            foreach ($instances as $instance) {
                $target = trim((string) ($instance['target'] ?? ''));
                $justification = trim((string) ($instance['justification'] ?? ''));
                $detail = implode(' – ', array_values(array_filter([
                    $target,
                    $justification === 'Rasse' || preg_match('/^Figurenstärke [1-5]$/u', $justification) ? '' : $justification,
                ])));
                if ($detail !== '') {
                    $instanceParts[] = $detail;
                }
            }

            $part = $name;
            if (count($instances) > 1) {
                $part .= ' ('.count($instances).'×)';
            }
            if ($instanceParts !== []) {
                $part .= ': '.implode('; ', $instanceParts);
            }
            $parts[] = $part;
        }

        return implode(', ', $parts);
    }

    private function specialText(mixed $names, mixed $details, mixed $counts = []): string
    {
        $names = is_array($names) ? $names : [];
        $details = is_array($details) ? $details : [];
        $counts = is_array($counts) ? $counts : [];
        $parts = [];

        foreach ($names as $name) {
            if (! is_scalar($name)) {
                continue;
            }

            $name = trim((string) $name);

            if ($name === '') {
                continue;
            }

            $part = $name;
            $count = max(1, (int) ($counts[$name] ?? 1));
            $detail = trim((string) ($details[$name] ?? ''));

            if ($count > 1) {
                $part .= " ({$count}×)";
            }

            if ($detail !== '') {
                $part .= ': '.$detail;
            }

            $parts[] = $part;
        }

        return implode(', ', $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $weapons
     * @return list<array<string, mixed>>
     */
    private function weaponRows(array $weapons): array
    {
        $rows = [];
        $rowIndexes = [];

        foreach ($weapons as $weapon) {
            if (! is_array($weapon)) {
                continue;
            }

            $id = (string) ($weapon['id'] ?? '');

            if (($weapon['alternate'] ?? false) && isset($rowIndexes[$id])) {
                $index = $rowIndexes[$id];
                $rows[$index]['alternate_text'] = trim(implode(' ', array_filter([
                    'Wurf',
                    'Angriff '.$this->modifier((int) ($weapon['attack'] ?? 0)),
                    'Schaden 1W6'.$this->modifier((int) ($weapon['damage_modifier'] ?? 0)).'+VM',
                    ($weapon['range_increment'] ?? '') !== '' ? 'RI '.$weapon['range_increment'] : '',
                    ($weapon['max_range'] ?? '') !== '' ? 'MR '.$weapon['max_range'] : '',
                ])));

                continue;
            }

            $weapon['attack_text'] = $this->modifier((int) ($weapon['attack'] ?? 0));
            $weapon['damage_text'] = '1W6'.$this->modifier((int) ($weapon['damage_modifier'] ?? 0)).'+VM';
            $weapon['alternate_text'] = '';
            $rows[] = $weapon;
            $rowIndexes[$id] = array_key_last($rows);
        }

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $equipment
     */
    private function equipmentText(array $equipment): string
    {
        $parts = [];
        $clothing = is_array($equipment['clothing'] ?? null) ? $equipment['clothing'] : null;

        if ($clothing !== null && trim((string) ($clothing['name'] ?? '')) !== '') {
            $parts[] = (string) $clothing['name'];
        }

        foreach ($equipment['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if ($name !== '') {
                $parts[] = ($quantity > 1 ? $quantity.'× ' : '').$name;
            }
        }

        return $this->short(implode(', ', $parts), 420);
    }

    private function ammunitionText(mixed $ammunition): string
    {
        if (! is_array($ammunition)) {
            return '';
        }

        $parts = [];

        foreach ($ammunition as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $parts[] = trim((string) ($entry['source'] ?? '').': '.(string) ($entry['quantity'] ?? '').' '.(string) ($entry['unit'] ?? ''), ': ');
        }

        return $this->short(implode(', ', array_filter($parts)), 220);
    }

    private function modifier(int $value, bool $plusForZero = true): string
    {
        if ($value > 0 || ($plusForZero && $value === 0)) {
            return '+'.$value;
        }

        return (string) $value;
    }

    private function short(mixed $value, int $limit): string
    {
        if (! is_scalar($value) && $value !== null) {
            return '';
        }

        $normalized = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');

        return Str::length($normalized) <= $limit
            ? $normalized
            : Str::substr($normalized, 0, max(0, $limit - 1)).'…';
    }
}
