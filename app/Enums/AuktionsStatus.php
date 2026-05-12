<?php

namespace App\Enums;

enum AuktionsStatus: string
{
    case Laufend = 'laufend';
    case ZumErsten = 'zum_ersten';
    case ZumZweiten = 'zum_zweiten';
    case Verkauft = 'verkauft';
    case NichtVerkauft = 'nicht_verkauft';

    public function label(): string
    {
        return match ($this) {
            self::Laufend => 'Laufend',
            self::ZumErsten => 'Zum ersten',
            self::ZumZweiten => 'Zum zweiten',
            self::Verkauft => 'Verkauft',
            self::NichtVerkauft => 'Nicht verkauft',
        };
    }

    public function erlaubtGebote(): bool
    {
        return in_array($this, [self::Laufend, self::ZumErsten, self::ZumZweiten], true);
    }

    public function istAbgeschlossen(): bool
    {
        return in_array($this, [self::Verkauft, self::NichtVerkauft], true);
    }
}
