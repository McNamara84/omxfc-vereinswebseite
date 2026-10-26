<?php

namespace App\Services\Profile;

use App\Models\User;

final class SeriesProfileCompletion
{
    public const FIELDS = [
        'einstiegsroman' => 'Einstiegsroman',
        'lesestand' => 'Aktueller Lesestand',
        'lieblingsautor' => 'Lieblingsautor:in',
        'lieblingsroman' => 'Lieblingsroman',
        'lieblingshardcover' => 'Lieblingshardcover',
        'lieblingscover' => 'Lieblingscover',
        'lieblingszyklus' => 'Lieblingszyklus',
        'lieblingsfigur' => 'Lieblingsfigur',
        'lieblingsmutation' => 'Lieblingsmutation',
        'lieblingsschauplatz' => 'Lieblingsschauplatz',
        'lieblingsthema' => 'Lieblingsthema',
    ];

    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $value);

        return $normalized === '' ? null : $normalized;
    }

    /** @return array{total: int, filled: int, missing: array<string, string>} */
    public function forUser(User $user): array
    {
        $missing = [];
        foreach (self::FIELDS as $field => $label) {
            if (self::normalize($user->getAttribute($field)) === null) {
                $missing[$field] = $label;
            }
        }

        return ['total' => count(self::FIELDS), 'filled' => count(self::FIELDS) - count($missing), 'missing' => $missing];
    }
}
