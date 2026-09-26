<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RpgExperienceAwardCalculator
{
    public function calculate(int $minutes, int $cycleBonus, array $input): array
    {
        $data = Validator::make([...$input, 'minutes' => $minutes, 'cycle_bonus' => $cycleBonus], [
            'minutes' => ['required', 'integer', 'min:0', 'max:10000000'],
            'cycle_bonus' => ['required', Rule::in([0, 1, 2, 4])],
            'survived' => ['required', 'boolean'],
            'roleplay' => ['required', 'integer', 'between:0,2'],
            'humor' => ['required', 'boolean'],
            'rescue' => ['required', 'boolean'],
            'unwounded' => ['required', 'boolean'],
            'points' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['nullable', 'string', 'max:4000'],
        ])->validate();
        $criteria = array_map('intval', array_intersect_key($data, array_flip(['survived', 'roleplay', 'humor', 'rescue', 'unwounded'])));
        $calculated = array_sum($criteria) + intdiv($minutes, 360) + $cycleBonus;
        $points = isset($data['points']) ? (int) $data['points'] : $calculated;
        $reason = trim($data['reason'] ?? '');
        if ($points !== $calculated && $reason === '') {
            throw ValidationException::withMessages(['points' => 'Eine abweichende EP-Vergabe benötigt eine Begründung.']);
        }

        return ['criteria' => $criteria, 'calculated_points' => $calculated, 'points' => $points, 'reason' => $reason ?: null];
    }
}
