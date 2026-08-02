<?php

namespace App\Services\ErrorReporting;

use Throwable;

class ErrorFingerprint
{
    public function forException(Throwable $exception, ?string $route, ?string $executionName): string
    {
        return hash('sha256', implode('|', [
            $exception::class,
            $exception->getFile(),
            (string) $exception->getLine(),
            $route ?? '',
            $executionName ?? '',
        ]));
    }
}
