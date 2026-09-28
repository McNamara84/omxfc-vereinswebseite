<?php

namespace App\Enums;

enum VeranstaltungsBaxxStatus: string
{
    case Offen = 'offen';
    case Abgeschlossen = 'abgeschlossen';
    case BestandAusgeschlossen = 'bestand_ausgeschlossen';
}
