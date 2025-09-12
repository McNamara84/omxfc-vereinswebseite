<?php

namespace App\Enums;

enum Role: string
{
    case Mitglied = 'Mitglied';
    case Ehrenmitglied = 'Ehrenmitglied';
    case Kassenwart = 'Kassenwart';
    case Vorstand = 'Vorstand';
    case Admin = 'Admin';
}
