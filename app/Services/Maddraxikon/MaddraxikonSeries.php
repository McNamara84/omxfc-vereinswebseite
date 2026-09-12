<?php

namespace App\Services\Maddraxikon;

use App\Enums\BookType;

final class MaddraxikonSeries
{
    private const BASE_URL = 'https://de.maddraxikon.com/';

    public static function categoryUrl(BookType $type): string
    {
        return self::BASE_URL.match ($type) {
            BookType::MaddraxDieDunkleZukunftDerErde => 'index.php?title=Kategorie:Maddrax-Heftromane',
            BookType::MaddraxHardcover => 'index.php?title=Kategorie:Maddrax-Hardcover',
            BookType::MissionMars => 'index.php?title=Kategorie:Mission_Mars-Heftromane',
            BookType::DasVolkDerTiefe => 'index.php?title=Kategorie:Das_Volk_der_Tiefe-Heftromane',
            BookType::ZweiTausendZwölfDasJahrDerApokalypse => 'index.php?title=Kategorie:2012-Heftromane',
            BookType::DieAbenteurer => 'index.php?title=Kategorie:Die_Abenteurer-Heftromane',
        };
    }

    public static function filename(BookType $type): string
    {
        return $type->key().'.json';
    }

    public static function requiresCycle(BookType $type): bool
    {
        return $type === BookType::MaddraxDieDunkleZukunftDerErde;
    }
}
