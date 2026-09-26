<?php

namespace App\Services;

use App\Support\RpgCharEditorEquipment;
use App\Support\RpgCharEditorSpecialRules;
use App\Support\RpgExperienceRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RpgCharacterAdvancementEvaluator
{
    public function evaluate(array $original, array $operations): array
    {
        $validated = Validator::make(['operations' => $operations], [
            'operations' => ['required', 'array', 'min:1', 'max:30'],
            'operations.*' => ['required', 'array:type,name,steps,target,reason,languages,items'],
            'operations.*.type' => ['required', Rule::in(['attribute', 'skill', 'advantage', 'disadvantage'])],
            'operations.*.name' => ['required', 'string', 'max:150'],
            'operations.*.steps' => ['nullable', 'integer', 'between:1,100'],
            'operations.*.target' => ['nullable', 'string', 'max:100'],
            'operations.*.reason' => ['required', 'string', 'max:4000'],
            'operations.*.languages' => ['nullable', 'array', 'max:300'],
            'operations.*.languages.*' => ['required', 'string', 'max:100', 'distinct'],
            'operations.*.items' => ['nullable', 'array', 'size:4'],
            'operations.*.items.*' => ['required', 'string', 'max:100'],
        ])->validate();
        $adapter = new RpgCharacterProgressionAdapter;
        $payload = $adapter->normalize($original);
        $changes = [];
        foreach ($validated['operations'] as $index => $operation) {
            $reason = trim($operation['reason']);
            $this->require($reason !== '', 'Bitte jede Änderung aus der Handlung begründen.');
            $names = new RpgCharacterSheetService;
            $name = match ($operation['type']) {
                'skill' => $names->canonicalSkillName($operation['name']),
                'advantage', 'disadvantage' => $names->canonicalSpecialName(trim($operation['name'])),
                default => trim($operation['name']),
            };
            $operation['name'] = $name;
            $steps = (int) ($operation['steps'] ?? 1);
            $target = $operation['target'] ?? '';
            $this->require($operation['type'] === 'advantage' || $target === '', 'Nur Vorteile können ein Ziel haben.');
            $this->require(($operation['type'] === 'advantage' && $name === 'High-Tech-Ausrüstung') || empty($operation['items']), 'Gegenstände können nur mit High-Tech-Ausrüstung erworben werden.');
            if (in_array($operation['type'], ['advantage', 'disadvantage'], true)) {
                $this->require($steps === 1, 'Vorteile und Nachteile bitte als einzelne Änderungen beantragen.');
            }
            [$before, $after, $cost] = match ($operation['type']) {
                'attribute' => $this->attribute($payload, $name, $steps, $adapter),
                'skill' => $this->skill($payload, $name, $steps),
                'advantage' => $this->advantage($payload, $name, $target, $reason, $operation),
                'disadvantage' => $this->disadvantage($payload, $name),
            };
            if (isset($operation['languages'])) {
                $this->require(($operation['type'] === 'skill' && $name === 'Sprachen') || ($operation['type'] === 'advantage' && $name === 'Sprachbegabt'), 'Sprachen können nur mit einer zugehörigen Verbesserung ergänzt werden.');
                $existing = $payload['languages'] ?? [];
                $languages = array_map('trim', $operation['languages']);
                $this->require(! in_array('', $languages, true) && count($languages) === count(array_unique($languages)), 'Sprachen müssen eindeutig benannt sein.');
                $this->require(array_diff($existing, $languages) === [], 'Bereits erlernte Sprachen bleiben erhalten.');
                $payload['languages'] = $languages;
            }
            $this->validateRequirements($payload, $operation);
            $changes[] = ['type' => $operation['type'], 'name' => $name, 'target' => $target, 'before' => $before, 'after' => $after, 'cost' => $cost, 'reason' => $reason,
                'languages' => $operation['languages'] ?? [], 'items' => $operation['items'] ?? []];
        }
        $payload['progression']['rule_version'] = RpgExperienceRules::VERSION;

        return ['payload' => $payload, 'changes' => $changes, 'cost' => array_sum(array_column($changes, 'cost'))];
    }

    private function attribute(array &$payload, string $name, int $steps, RpgCharacterProgressionAdapter $adapter): array
    {
        $this->require(in_array($name, RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS, true), 'Unbekanntes Attribut.');
        $race = $adapter->raceModifiers($payload);
        $before = $payload['attributes'][$name];
        $after = $before + $steps;
        $base = $after - ($race[$name] ?? 0) - $adapter->attributeBonus($payload, $name);
        $this->require($base <= 1 && $after <= 2 && $after >= -2, 'Die Attributsgrenze wäre überschritten.');
        $payload['attributes'][$name] = $after;

        return [$before, $after, RpgExperienceRules::ATTRIBUTE_COST * $steps];
    }

    private function skill(array &$payload, string $name, int $steps): array
    {
        $catalog = array_column(RpgCharacterSheetService::skillRuleConfig()['skills'], null, 'name');
        $base = explode(':', $name, 2)[0];
        $psychic = in_array($name, RpgCharEditorSpecialRules::PSYCHIC_POWER_TARGETS, true);
        $allowed = isset($catalog[$name]) || (isset($catalog[$base]) && ($catalog[$base]['specializable'] ?? false) && trim(explode(':', $name, 2)[1] ?? '') !== '');
        $this->require($allowed || $psychic, 'Diese Fertigkeit ist nicht erlaubt.');
        if ($psychic) {
            $powers = (new RpgCharacterPsychicCalculator)->calculate($payload)['powers'];
            $this->require(in_array($name, array_column($powers, 'name'), true), 'Die passende psychische Kraft muss zuerst erworben werden.');
        }
        $before = $this->skillValue($payload, $name, exact: true);
        $after = $before + $steps;
        $cost = 0;
        for ($value = $before + 1; $value <= $after; $value++) {
            $cost += RpgExperienceRules::skillCost($value, $psychic);
        }
        $payload['skills'] = array_values(array_filter($payload['skills'], fn (array $skill): bool => $skill['name'] !== $name));
        $payload['skills'][] = ['name' => $name, 'value' => $after];

        return [$before, $after, $cost];
    }

    private function advantage(array &$payload, string $name, string $target, string $reason, array $operation): array
    {
        $rule = RpgCharEditorSpecialRules::advantages()[$name] ?? null;
        $this->require($rule !== null, 'Unbekannter Vorteil.');
        $instances = array_values(array_filter($payload['advantage_effects'], fn (array $effect): bool => $effect['name'] === $name));
        $before = count($instances);
        $this->require($rule['repeat'] !== 'none' || $before === 0, 'Dieser Vorteil ist bereits vorhanden.');
        $this->require($rule['targets'] === [] ? $target === '' : in_array($target, $rule['targets'], true), 'Bitte ein erlaubtes Ziel für den Vorteil wählen.');
        if ($rule['repeat'] === 'unique_target') {
            $targets = array_column($instances, 'target');
            $this->require(! in_array('', $targets, true), 'Die AG-Leitung muss zuerst die Ziele früherer Vorteile dokumentieren.');
            $this->require(! in_array($target, $targets, true), 'Dieser Vorteil ist für dieses Ziel bereits vorhanden.');
        }
        if ($name === 'Gesteigertes Attribut') {
            $this->require($payload['attributes'][$target] < 2, 'Der Vorteil würde die absolute Attributsgrenze +2 überschreiten.');
            $payload['attributes'][$target]++;
        }
        if ($name === 'High-Tech-Ausrüstung') {
            $items = $operation['items'] ?? [];
            $this->require(count($items) === 4, 'Bitte genau vier High-Tech-Gegenstände wählen.');
            foreach ($items as $id) {
                $item = RpgCharEditorEquipment::itemMap()[$id] ?? null;
                $this->require($item !== null && RpgCharEditorEquipment::requiresHighTechAdvantage($item), 'Bitte gültige High-Tech-Gegenstände wählen.');
                $payload['equipment']['items'][] = ['id' => $id, 'name' => $item['name'], 'quantity' => 1];
            }
            $merged = [];
            foreach ($payload['equipment']['items'] as $item) {
                $id = $item['id'];
                if (isset($merged[$id])) {
                    $merged[$id]['quantity'] += $item['quantity'];
                } else {
                    $merged[$id] = $item;
                }
            }
            $payload['equipment']['items'] = array_values($merged);
            $payload['equipment']['ammunition'] = (new RpgCharacterSheetService)->equipmentAmmunitionPayload($payload['equipment']['items']);
        } else {
            $this->require(empty($operation['items']), 'Gegenstände können nur mit High-Tech-Ausrüstung erworben werden.');
        }
        $payload['advantages'] = array_values(array_unique([...$payload['advantages'], $name]));
        $payload['advantage_effects'][] = ['name' => $name, 'target' => $target, 'justification' => $reason];
        $payload['advantage_counts'][$name] = $before + 1;
        $payload['advantage_details'][$name] = trim(implode('; ', array_filter([$payload['advantage_details'][$name] ?? '', trim($target.' – '.$reason, ' –')])));

        return [$before, $before + 1, RpgExperienceRules::advantageCost($name)];
    }

    private function disadvantage(array &$payload, string $name): array
    {
        $this->require(isset(RpgCharEditorSpecialRules::disadvantages()[$name]) && in_array($name, $payload['disadvantages'], true), 'Dieser Nachteil ist nicht vorhanden.');
        $payload['disadvantages'] = array_values(array_diff($payload['disadvantages'], [$name]));
        unset($payload['disadvantage_details'][$name]);
        $payload['progression']['removed_disadvantages'][] = $name;

        return ['vorhanden', 'abgelegt', 20];
    }

    private function validateRequirements(array $payload, array $operation): void
    {
        $education = $this->skillValue($payload, 'Bildung');
        if ($operation['type'] === 'skill') {
            $name = $operation['name'];
            if (in_array($name, ['Bildung', 'Intuition'], true)) {
                $this->require($education === 0 || $this->skillValue($payload, 'Intuition') === 0 || in_array('Kind zweier Welten', $payload['advantages'], true), 'Bildung und Intuition benötigen den Vorteil Kind zweier Welten.');
            }
            if ($name === 'Bildung') {
                $this->require(! in_array('Primitiv', $payload['disadvantages'], true), 'Mit dem Nachteil Primitiv kann Bildung nicht erlernt werden.');
            }
            if (str_starts_with($name, 'Wissenschaftler')) {
                $this->require($this->skillValue($payload, 'Wissenschaftler') <= $education, 'Wissenschaftler darf Bildung nicht übersteigen.');
            }
        }
        if (($operation['type'] === 'skill' && $operation['name'] === 'Sprachen') || ($operation['type'] === 'advantage' && $operation['name'] === 'Sprachbegabt')) {
            $value = $this->skillValue($payload, 'Sprachen');
            $count = count($payload['languages'] ?? []);
            $max = $value * (in_array('Sprachbegabt', $payload['advantages'], true) ? 3 : 1);
            $this->require($count >= $value && $count <= $max, "Bitte zwischen {$value} und {$max} Sprachen oder Dialekten benennen.");
        }
    }

    private function skillValue(array $payload, string $name, bool $exact = false): int
    {
        $values = [0];
        foreach ($payload['skills'] as $skill) {
            if ($skill['name'] === $name || (! $exact && str_starts_with($skill['name'], $name.':'))) {
                $values[] = (int) $skill['value'];
            }
        }

        return max($values);
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['operations' => $message]);
        }
    }
}
