<?php

namespace App\Exceptions;

use RuntimeException;

class CoverSyncTimeoutException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Zeitlimit des Cover-Abgleichs erreicht. Bitte erneut starten.');
    }
}
