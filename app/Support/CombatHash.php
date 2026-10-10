<?php

namespace App\Support;

final class CombatHash
{
    public static function make(array $input): string
    {
        return hash('sha256', json_encode(self::canonical($input), JSON_THROW_ON_ERROR));
    }

    private static function canonical(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = self::canonical($value);
            }
        }

        return $data;
    }
}
