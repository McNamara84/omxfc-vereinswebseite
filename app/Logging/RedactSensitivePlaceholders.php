<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class RedactSensitivePlaceholders implements ProcessorInterface
{
    /** @var array<string, true> */
    private array $sensitiveKeys = [];

    /**
     * @param  list<string>  $sensitiveKeys
     */
    public function __construct(array $sensitiveKeys, private readonly string $mask = '[REDACTED]')
    {
        foreach ($sensitiveKeys as $key) {
            $this->sensitiveKeys[strtolower($key)] = true;
        }
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if (! str_contains($record->message, '{')) {
            return $record;
        }

        $replacements = [];

        foreach ($record->context as $key => $_value) {
            if (! is_string($key) || ! isset($this->sensitiveKeys[strtolower($key)])) {
                continue;
            }

            $placeholder = '{'.$key.'}';

            if (str_contains($record->message, $placeholder)) {
                $replacements[$placeholder] = $this->mask;
            }
        }

        if ($replacements === []) {
            return $record;
        }

        return $record->with(message: strtr($record->message, $replacements));
    }
}
