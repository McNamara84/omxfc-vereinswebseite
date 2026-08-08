<?php

namespace App\Services;

use App\Support\RpgCharEditorSpecialRules;

final class RpgCharacterCreationEvaluator
{
    private const ABSOLUTE_ATTRIBUTE_MIN = -2;

    private const ABSOLUTE_ATTRIBUTE_MAX = 2;

    private const RACE_ATTRIBUTE_MODIFIERS = [
        'Guul' => ['au' => -1],
        'Nosfera' => ['ge' => 1, 'au' => -1],
        'Taratze' => ['st' => 1, 'wa' => 1, 'in' => -1, 'au' => -1],
        'Wulfane' => ['ro' => 1, 'au' => -1],
        'Techno' => ['st' => -1, 'ro' => -1, 'in' => 1],
    ];

    private const RACE_ADVANTAGES = [
        'Guul' => ['Natürliche Waffen'],
        'Hydrit' => ['Kiemen', 'Natürliche Waffen'],
        'Nosfera' => ['Nachtsicht'],
        'Techno' => ['High-Tech-Ausrüstung'],
        'Präkristofluu' => ['High-Tech-Ausrüstung'],
    ];

    private const RACE_DISADVANTAGES = [
        'Guul' => ['Primitiv', 'Gejagt'],
        'Hydrit' => ['Anfälligkeit gegen Wahnsinn'],
        'Nosfera' => ['Blutdurst', 'Lichtscheu', 'Gejagt'],
        'Taratze' => ['Auffällig', 'Primitiv', 'Gejagt'],
        'Wulfane' => ['Ehrenkodex'],
        'Techno' => ['Tödliche Immunschwäche'],
    ];

    /**
     * Evaluate normalized creation input without reading request or session state.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function evaluate(array $input): array
    {
        $errors = [];
        $creationLevel = $this->normalizeCreationLevel($input['creation_level'] ?? null, $errors);
        $levelRules = RpgCharEditorSpecialRules::creationLevel($creationLevel);
        $race = (string) ($input['race'] ?? '');
        $culture = (string) ($input['culture'] ?? '');
        $gender = (string) ($input['gender'] ?? '');
        $barbarBonus = (string) ($input['barbar_attribute_bonus'] ?? '');
        $adjustments = $this->normalizeAdjustmentMap($input['attribute_adjustments'] ?? [], $errors);
        $raceModifiers = $this->raceAttributeModifiers($race, $barbarBonus, $errors);
        $effects = $this->normalizeEffects($input['advantage_effects'] ?? [], $errors);
        $selectedAdvantages = $this->uniqueStrings($input['advantages'] ?? []);
        $selectedDisadvantages = $this->uniqueStrings($input['disadvantages'] ?? []);
        $racialAdvantages = self::RACE_ADVANTAGES[$race] ?? [];
        $racialDisadvantages = self::RACE_DISADVANTAGES[$race] ?? [];
        $negatedRacialDisadvantages = $this->uniqueStrings($input['negated_racial_disadvantages'] ?? []);
        $extraApAttribute = (string) ($input['extra_ap_attribute'] ?? '');
        $compensationAttributes = $this->uniqueStrings($input['advantage_compensation_attributes'] ?? []);

        $this->validateNegations($negatedRacialDisadvantages, $racialDisadvantages, $errors);
        $this->validateTradeAttributes($adjustments, $extraApAttribute, $compensationAttributes, $errors);

        $levelAutomaticAdvantages = $levelRules['automaticAdvantages'];
        $levelAutomaticDisadvantages = $levelRules['automaticDisadvantages'];
        $automaticAdvantages = array_values(array_unique([...$levelAutomaticAdvantages, ...$racialAdvantages]));
        $freeAdvantages = array_values(array_diff($selectedAdvantages, $automaticAdvantages));
        $effectInstances = $this->validateAndCompleteEffects(
            $freeAdvantages,
            $racialAdvantages,
            $levelAutomaticAdvantages,
            $effects,
            $culture,
            $gender,
            $creationLevel,
            $errors,
        );

        $advantageBonuses = array_fill_keys(RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS, 0);
        foreach ($effectInstances as $effect) {
            if (($effect['name'] ?? '') === 'Gesteigertes Attribut'
                && in_array($effect['target'] ?? '', RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS, true)) {
                $advantageBonuses[$effect['target']]++;
            }
        }

        $finalAttributes = [];
        $attributePointsUsed = 0;
        foreach (RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS as $attribute) {
            $adjustment = $adjustments[$attribute];
            $attributePointsUsed += max($adjustment, 0);
            $final = ($raceModifiers[$attribute] ?? 0) + $adjustment + $advantageBonuses[$attribute];
            $finalAttributes[$attribute] = $final;

            if ($final < self::ABSOLUTE_ATTRIBUTE_MIN || $final > self::ABSOLUTE_ATTRIBUTE_MAX) {
                $errors['attributes'][] = "Das Attribut {$attribute} liegt nach allen Modifikatoren außerhalb des erlaubten Bereichs -2 bis +2.";
            }
        }

        $extraAttributePoints = $extraApAttribute === '' ? 0 : 1;
        $availableAttributePoints = $levelRules['attributePoints'] + $extraAttributePoints;
        if ($attributePointsUsed > $availableAttributePoints) {
            $errors['attributes'][] = 'Die Erschaffungsänderungen überschreiten die verfügbaren Attributspunkte.';
        } elseif ($attributePointsUsed < $availableAttributePoints) {
            $errors['attributes'][] = 'Die verfügbaren Attributspunkte müssen vollständig verteilt werden.';
        }

        $usedAdvantageUnits = count($negatedRacialDisadvantages);
        foreach ($freeAdvantages as $advantage) {
            $rule = RpgCharEditorSpecialRules::advantages()[$advantage] ?? null;
            if ($rule === null) {
                $errors['advantages'][] = "Der Vorteil {$advantage} ist nicht erlaubt.";

                continue;
            }

            $instanceCount = count(array_filter(
                $effectInstances,
                static fn (array $effect): bool => ($effect['name'] ?? '') === $advantage,
            ));
            $usedAdvantageUnits += (int) $rule['cost'] * max($instanceCount, 1);
        }

        $maxAdvantageUnits = $levelRules['freeAdvantageUnits']
            + RpgCharEditorSpecialRules::MAX_EXTRA_ADVANTAGE_UNITS;
        if ($usedAdvantageUnits > $maxAdvantageUnits) {
            $errors['advantages'][] = "Auf Figurenstärke {$creationLevel} sind höchstens {$maxAdvantageUnits} Vorteilswerte verfügbar.";
        }

        $activeRacialDisadvantages = array_values(array_diff($racialDisadvantages, $negatedRacialDisadvantages));
        foreach ($activeRacialDisadvantages as $disadvantage) {
            if (! in_array($disadvantage, $selectedDisadvantages, true)) {
                $errors['disadvantages'][] = "Der rassengegebene Nachteil {$disadvantage} muss aktiv bleiben oder mit einem Vorteilswert negiert werden.";
            }
        }
        foreach ($negatedRacialDisadvantages as $disadvantage) {
            if (in_array($disadvantage, $selectedDisadvantages, true)) {
                $errors['negated_racial_disadvantages'][] = "Der negierte Rassennachteil {$disadvantage} darf nicht zugleich aktiv gewählt sein.";
            }
        }

        $voluntaryDisadvantages = array_values(array_diff(
            $selectedDisadvantages,
            $racialDisadvantages,
            $levelAutomaticDisadvantages,
        ));
        $requiredCompensations = max($usedAdvantageUnits - $levelRules['freeAdvantageUnits'], 0);
        $availableCompensations = count($voluntaryDisadvantages) + count($compensationAttributes);
        if ($availableCompensations < $requiredCompensations) {
            $errors['disadvantages'][] = "Für {$requiredCompensations} zusätzliche Vorteilswerte fehlen ".($requiredCompensations - $availableCompensations).' Ausgleiche durch freiwillige Nachteile oder Attributsenkungen.';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'creation_level' => $creationLevel,
            'level_rules' => $levelRules,
            'attributes' => [
                'final' => $finalAttributes,
                'creation_adjustments' => $adjustments,
                'race_modifiers' => $raceModifiers,
                'advantage_bonuses' => $advantageBonuses,
            ],
            'attribute_budget' => [
                'base' => $levelRules['attributePoints'],
                'extra' => $extraAttributePoints,
                'used' => $attributePointsUsed,
                'remaining' => $availableAttributePoints - $attributePointsUsed,
            ],
            'advantage_budget' => [
                'free' => $levelRules['freeAdvantageUnits'],
                'used' => $usedAdvantageUnits,
                'extra' => max($usedAdvantageUnits - $levelRules['freeAdvantageUnits'], 0),
                'required_compensations' => $requiredCompensations,
                'available_compensations' => $availableCompensations,
            ],
            'automatic_advantages' => $automaticAdvantages,
            'automatic_disadvantages' => $levelAutomaticDisadvantages,
            'effective_advantages' => array_values(array_unique([...$automaticAdvantages, ...$freeAdvantages])),
            'effective_disadvantages' => array_values(array_unique([
                ...$levelAutomaticDisadvantages,
                ...$activeRacialDisadvantages,
                ...$voluntaryDisadvantages,
            ])),
            'voluntary_disadvantages' => $voluntaryDisadvantages,
            'negated_racial_disadvantages' => $negatedRacialDisadvantages,
            'advantage_effects' => $effectInstances,
        ];
    }

    /** @param array<string, list<string>> $errors */
    private function normalizeCreationLevel(mixed $value, array &$errors): int
    {
        if ($value === null || $value === '') {
            return RpgCharEditorSpecialRules::DEFAULT_CREATION_LEVEL;
        }

        $level = filter_var($value, FILTER_VALIDATE_INT);
        if ($level === false || ! in_array((int) $level, RpgCharEditorSpecialRules::validCreationLevels(), true)) {
            $errors['creation_level'][] = 'Die Figurenstärke muss zwischen 1 und 5 liegen.';

            return RpgCharEditorSpecialRules::DEFAULT_CREATION_LEVEL;
        }

        return (int) $level;
    }

    /** @param array<string, list<string>> $errors */
    private function normalizeAdjustmentMap(mixed $value, array &$errors): array
    {
        $source = is_array($value) ? $value : [];
        $result = [];

        foreach (RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS as $attribute) {
            $raw = $source[$attribute] ?? 0;
            if (filter_var($raw, FILTER_VALIDATE_INT) === false || (int) $raw < -1 || (int) $raw > 1) {
                $errors['attribute_adjustments'][] = "Die Erschaffungsänderung für {$attribute} muss -1, 0 oder +1 sein.";
                $result[$attribute] = 0;

                continue;
            }
            $result[$attribute] = (int) $raw;
        }

        return $result;
    }

    /** @param array<string, list<string>> $errors */
    private function raceAttributeModifiers(string $race, string $barbarBonus, array &$errors): array
    {
        $modifiers = self::RACE_ATTRIBUTE_MODIFIERS[$race] ?? [];
        if ($race !== 'Barbar') {
            return $modifiers;
        }

        if (! in_array($barbarBonus, RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS, true)) {
            $errors['barbar_attribute_bonus'][] = 'Barbaren müssen ein Attribut für ihren Rassenbonus +1 wählen.';

            return [];
        }

        return [$barbarBonus => 1];
    }

    /** @param array<string, list<string>> $errors */
    private function normalizeEffects(mixed $value, array &$errors): array
    {
        if (! is_array($value)) {
            $errors['advantage_effects'][] = 'Die Vorteilsinstanzen müssen als Liste übermittelt werden.';

            return [];
        }

        $effects = [];
        foreach ($value as $effect) {
            if (! is_array($effect)) {
                $errors['advantage_effects'][] = 'Jede Vorteilsinstanz muss strukturiert übermittelt werden.';

                continue;
            }
            $effects[] = [
                'name' => trim((string) ($effect['name'] ?? '')),
                'target' => trim((string) ($effect['target'] ?? '')),
                'justification' => trim((string) ($effect['justification'] ?? '')),
            ];
        }

        return $effects;
    }

    /** @param array<string, list<string>> $errors */
    private function validateNegations(array $negated, array $racialDisadvantages, array &$errors): void
    {
        foreach ($negated as $disadvantage) {
            if (! in_array($disadvantage, $racialDisadvantages, true)) {
                $errors['negated_racial_disadvantages'][] = "{$disadvantage} ist kein Nachteil der gewählten Rasse und kann daher nicht auf diese Weise negiert werden.";
            }
        }
    }

    /** @param array<string, list<string>> $errors */
    private function validateTradeAttributes(array $adjustments, string $extraApAttribute, array $compensationAttributes, array &$errors): void
    {
        if ($extraApAttribute !== '') {
            if (! in_array($extraApAttribute, RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS, true)
                || ($adjustments[$extraApAttribute] ?? 0) !== -1) {
                $errors['extra_ap_attribute'][] = 'Der zusätzliche AP benötigt eine freiwillige Erschaffungssenkung um 1.';
            }
        }

        if (count($compensationAttributes) > RpgCharEditorSpecialRules::MAX_EXTRA_ADVANTAGE_UNITS) {
            $errors['advantage_compensation_attributes'][] = 'Höchstens zwei Attributsenkungen können zusätzliche Vorteilswerte ausgleichen.';
        }

        foreach ($compensationAttributes as $attribute) {
            if (! in_array($attribute, RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS, true)
                || ($adjustments[$attribute] ?? 0) !== -1) {
                $errors['advantage_compensation_attributes'][] = "Der Ausgleich über {$attribute} benötigt dort eine freiwillige Erschaffungssenkung um 1.";
            }
        }

        if ($extraApAttribute !== '' && in_array($extraApAttribute, $compensationAttributes, true)) {
            $errors['attribute_adjustments'][] = 'Dieselbe Attributsenkung darf nicht zugleich einen zusätzlichen AP und einen Zusatzvorteil finanzieren.';
        }
    }

    /** @param array<string, list<string>> $errors */
    private function validateAndCompleteEffects(
        array $freeAdvantages,
        array $racialAdvantages,
        array $levelAutomaticAdvantages,
        array $effects,
        string $culture,
        string $gender,
        int $creationLevel,
        array &$errors,
    ): array {
        $rules = RpgCharEditorSpecialRules::advantages();
        $result = [];

        foreach ($effects as $effect) {
            $name = $effect['name'];
            $rule = $rules[$name] ?? null;
            if ($rule === null || ! in_array($name, $freeAdvantages, true)) {
                $errors['advantage_effects'][] = "Für {$name} wurde eine unbekannte oder nicht gewählte Vorteilsinstanz übermittelt.";

                continue;
            }

            if ($rule['targets'] !== [] && ! in_array($effect['target'], $rule['targets'], true)) {
                $errors['advantage_effects'][] = "Für {$name} muss ein erlaubtes Ziel gewählt werden.";
            }
            if ($rule['targets'] === [] && $effect['target'] !== '') {
                $errors['advantage_effects'][] = "Für {$name} darf kein Ziel übermittelt werden.";
            }
            if ($rule['requires_justification'] && $effect['justification'] === '') {
                $errors['advantage_effects'][] = "Für {$name} muss jede frei gewählte Instanz begründet werden.";
            }
            if ($rule['requires_detail'] && $effect['justification'] === '') {
                $errors['advantage_effects'][] = "Für {$name} muss eine nähere Angabe gemacht werden.";
            }

            $result[] = $effect;
        }

        foreach ($freeAdvantages as $advantage) {
            $rule = $rules[$advantage] ?? null;
            if ($rule === null) {
                continue;
            }

            $matching = array_values(array_filter($result, static fn (array $effect): bool => $effect['name'] === $advantage));
            if ($matching === []) {
                $errors['advantage_effects'][] = "Für {$advantage} fehlt eine Vorteilsinstanz.";

                continue;
            }

            if ($rule['repeat'] === 'none' && count($matching) > 1) {
                $errors['advantage_effects'][] = "{$advantage} darf nur einmal gewählt werden.";
            }

            if ($rule['repeat'] === 'unique_target') {
                $targets = array_column($matching, 'target');
                if (count($targets) !== count(array_unique($targets))) {
                    $errors['advantage_effects'][] = "Jedes Ziel von {$advantage} darf nur einmal gewählt werden.";
                }
            }
        }

        if ($culture === 'Volk der 13 Inseln' && $gender === 'weiblich') {
            $telepathy = array_filter($result, static fn (array $effect): bool => $effect['name'] === 'Psychische Kraft' && $effect['target'] === 'Telepathie');
            if (! in_array('Psychische Kraft', $freeAdvantages, true) || $telepathy === []) {
                $errors['advantage_effects'][] = 'Frauen aus dem Volk der 13 Inseln müssen Psychische Kraft regulär mit dem Ziel Telepathie wählen.';
            }
        }

        foreach ($racialAdvantages as $advantage) {
            $result[] = ['name' => $advantage, 'target' => '', 'justification' => 'Rasse'];
        }
        foreach (array_diff($levelAutomaticAdvantages, $racialAdvantages) as $advantage) {
            $result[] = ['name' => $advantage, 'target' => '', 'justification' => "Figurenstärke {$creationLevel}"];
        }

        return $result;
    }

    /** @return list<string> */
    private function uniqueStrings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '', $value),
            static fn (string $item): bool => $item !== '',
        )));
    }
}
