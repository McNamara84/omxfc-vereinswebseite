<?php

namespace App\Enums;

enum KassenbuchEditRequestType: string
{
    case Edit = 'edit';
    case Delete = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::Edit => 'Bearbeitung',
            self::Delete => 'Löschung',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}