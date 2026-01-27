<?php

namespace App\Enums;

enum KassenbuchEditReasonType: string
{
    case Tippfehler = 'tippfehler';
    case FalschesDatum = 'falsches_datum';
    case FalscherBetrag = 'falscher_betrag';
    case FalscheBeschreibung = 'falsche_beschreibung';
    case FalscherTyp = 'falscher_typ';
    case Sonstiges = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Tippfehler => 'Tippfehler',
            self::FalschesDatum => 'Falsches Datum',
            self::FalscherBetrag => 'Falscher Betrag',
            self::FalscheBeschreibung => 'Falsche Beschreibung',
            self::FalscherTyp => 'Falscher Typ (Einnahme/Ausgabe)',
            self::Sonstiges => 'Sonstiges',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
