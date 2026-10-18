<?php

namespace App\Support\CoverRatings;

use InvalidArgumentException;

final class CoverKeywords
{
    public static function clean(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidArgumentException('Ungültiger UTF-8-Text.');
        }

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    public static function normalize(string $value): string
    {
        return mb_strtolower(self::clean($value), 'UTF-8');
    }

    /** @return array<string, string> Normalized keyword => display value. */
    public static function values(array $values): array
    {
        if (! array_is_list($values) || count($values) > 500) {
            throw new InvalidArgumentException('Ungültige Schlagwortliste.');
        }

        $keywords = [];
        foreach ($values as $value) {
            if (! is_string($value) || mb_strlen($value) > 255) {
                throw new InvalidArgumentException('Ungültiges oder zu langes Schlagwort.');
            }
            $display = self::clean($value);
            if ($display !== '') {
                $keywords[self::normalize($display)] ??= $display;
            }
        }
        ksort($keywords, SORT_STRING);

        return $keywords;
    }

    public static function fileTitle(string $title): string
    {
        $title = self::clean(str_replace('_', ' ', $title));
        $name = preg_replace('/^(?:Datei|File|Bild):/iu', '', $title);
        if ($name === '' || mb_strlen('Datei:'.$name) > 255 || preg_match('/[\x00-\x1f\x7f\[\]{}|#<>]/u', $name)) {
            throw new InvalidArgumentException('Ungültiger Cover-Dateititel.');
        }

        return 'Datei:'.mb_strtoupper(mb_substr($name, 0, 1), 'UTF-8').mb_substr($name, 1);
    }
}
