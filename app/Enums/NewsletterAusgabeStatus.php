<?php

namespace App\Enums;

enum NewsletterAusgabeStatus: string
{
    case Entwurf = 'entwurf';
    case Veroeffentlicht = 'veroeffentlicht';
}