<?php

namespace App\Exceptions;

use RuntimeException;

class MaddraxikonApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $apiCode = null,
        public readonly ?int $statusCode = null,
    ) {
        parent::__construct($message);
    }
}
