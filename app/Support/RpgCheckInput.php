<?php

namespace App\Support;

use App\Services\RpgCharacterSheetService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RpgCheckInput
{
    public static function validate(array $input, bool $store = false): array
    {
        $data = Validator::make(['data' => $input], [
            'data' => ['required', 'array:submission_key,mode,description,visibility,difficulty,participants'],
            'data.submission_key' => ['required', 'uuid'],
            'data.mode' => ['required', Rule::in(['fixed', 'opposed'])],
            'data.description' => ['required', 'string', 'max:2000'],
            'data.visibility' => ['required', Rule::in(['open', 'hidden'])],
            'data.difficulty' => [Rule::requiredIf(($input['mode'] ?? '') === 'fixed'), 'nullable', 'integer', 'between:-1000000,1000000'],
            'data.participants' => ['required', 'array', 'min:1', 'max:100'],
            'data.participants.*' => ['required', 'array:character_id,revision,check_type,attribute_key,skill_name,modifiers'],
            'data.participants.*.character_id' => ['required', 'integer', 'distinct', 'min:1'],
            'data.participants.*.revision' => [$store ? 'required' : 'nullable', 'integer', 'min:0'],
            'data.participants.*.check_type' => ['required', Rule::in(['attribute', 'skill'])],
            'data.participants.*.attribute_key' => ['required', Rule::in(array_keys(RpgCheckRules::attributes()))],
            'data.participants.*.skill_name' => ['nullable', 'string', 'max:255'],
            'data.participants.*.modifiers' => ['present', 'array', 'max:20'],
            'data.participants.*.modifiers.*' => ['required', 'array:value,description'],
            'data.participants.*.modifiers.*.value' => ['required', 'integer', 'between:-1000,1000'],
            'data.participants.*.modifiers.*.description' => ['required', 'string', 'max:255'],
        ])->validate()['data'];
        self::ensure($data['mode'] !== 'opposed' || (count($data['participants']) === 2 && ($data['difficulty'] ?? null) === null),
            'Widerstandsproben benötigen genau zwei Charaktere und keinen Schwierigkeitsgrad.');
        $data['difficulty'] = $data['mode'] === 'fixed' ? (int) $data['difficulty'] : null;
        $data['description'] = trim($data['description']);
        self::ensure($data['description'] !== '', 'Bitte eine Beschreibung eingeben.');
        $names = new RpgCharacterSheetService;
        foreach ($data['participants'] as &$participant) {
            $participant['character_id'] = (int) $participant['character_id'];
            $participant['revision'] = isset($participant['revision']) ? (int) $participant['revision'] : null;
            $participant['skill_name'] = filled($participant['skill_name'] ?? null) ? $names->canonicalSkillName(trim($participant['skill_name'])) : null;
            self::ensure(($participant['check_type'] === 'skill') === ($participant['skill_name'] !== null),
                'Nur Fertigkeitsproben benötigen eine Fertigkeit.');
            foreach ($participant['modifiers'] as &$modifier) {
                $modifier = ['value' => (int) $modifier['value'], 'description' => trim($modifier['description'])];
                self::ensure($modifier['description'] !== '', 'Jeder Modifikator benötigt eine Beschreibung.');
            }
            unset($modifier);
        }
        unset($participant);
        if ($data['mode'] === 'fixed') {
            $settings = fn ($p) => array_diff_key($p, array_flip(['character_id', 'revision']));
            foreach ($data['participants'] as $participant) {
                self::ensure($settings($participant) === $settings($data['participants'][0]), 'Sammelproben verwenden dieselben Vorgaben.');
            }
            usort($data['participants'], fn ($a, $b) => $a['character_id'] <=> $b['character_id']);
        }

        return $data;
    }

    public static function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['probe' => $message]);
        }
    }
}
