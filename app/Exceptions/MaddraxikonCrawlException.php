<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class MaddraxikonCrawlException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $url = null,
        public readonly ?int $statusCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
