<?php

namespace App\Support\Romantausch;

use RuntimeException;
use Throwable;

final class WorkflowFailure extends RuntimeException
{
    public function __construct(string $message, public readonly string $field, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
