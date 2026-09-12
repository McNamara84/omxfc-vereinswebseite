<?php

namespace App\Exceptions;

use RuntimeException;

class MaddraxikonCrawlException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $url = null,
        public readonly ?int $statusCode = null,
    ) {
        parent::__construct($message);
    }
}
