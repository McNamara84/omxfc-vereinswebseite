<?php

namespace App\Enums;

enum AudiobookEpisodeStatus: string
{
    case Skripterstellung = 'Skripterstellung';
    case Korrekturlesung = 'Korrekturlesung';
    case Rollenbesetzung = 'Rollenbesetzung';
    case Aufnahmensammlung = 'Aufnahmensammlung';
    case Musikerstellung = 'Musikerstellung';
    case Audiobearbeitung = 'Audiobearbeitung';
    case Videobearbeitung = 'Videobearbeitung';
    case Grafiken = 'Grafiken';
    case Veroeffentlichungsplanung = 'Veröffentlichungsplanung';
    case Veroeffentlichung = 'Veröffentlichung';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
