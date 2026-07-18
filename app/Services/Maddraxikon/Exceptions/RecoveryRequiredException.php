<?php

namespace App\Services\Maddraxikon\Exceptions;

use LogicException;

final class RecoveryRequiredException extends LogicException
{
    public function __construct()
    {
        parent::__construct(
            'Die Maddraxikon-Baxx-Auswertung bleibt bis zum Abschluss des offenen Recovery-Fensters gesperrt.',
        );
    }
}
