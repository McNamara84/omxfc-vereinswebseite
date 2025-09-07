<?php

namespace App\Enums;

enum KassenbuchEntryType: string
{
    case Einnahme = 'einnahme';
    case Ausgabe = 'ausgabe';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
