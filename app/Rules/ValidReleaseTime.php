<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidReleaseTime implements ValidationRule
{
    private const PATTERN = '/^(((0[1-9]|[12]\d|3[01])\.(0[1-9]|1[0-2])\.(19|20)\d{2})|(0[1-9]|1[0-2])\.(19|20)\d{2}|(19|20)\d{2})$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match(self::PATTERN, $value)) {
            $fail('Das :attribute hat ein ungültiges Format.');

            return;
        }

        if (preg_match('/^(0[1-9]|[12]\d|3[01])\.(0[1-9]|1[0-2])\.(19|20)\d{2}$/', $value)) {
            [$day, $month, $year] = explode('.', $value);
            if (! checkdate((int) $month, (int) $day, (int) $year)) {
                $fail('Das :attribute ist kein gültiges Datum.');
            }
        }
    }
}
