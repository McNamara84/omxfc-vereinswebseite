<?php

namespace App\Enums;

enum Role: string
{
    case Anwaerter = 'Anwärter';
    case Mitwirkender = 'Mitwirkender';
    case Mitglied = 'Mitglied';
    case Ehrenmitglied = 'Ehrenmitglied';
    case Kassenwart = 'Kassenwart';
    case Vorstand = 'Vorstand';
    case Admin = 'Admin';
}
