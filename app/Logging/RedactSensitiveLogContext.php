<?php

namespace App\Logging;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Formatter\RedactingFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger as MonologLogger;

class RedactSensitiveLogContext
{
    public function __construct(private readonly Repository $config) {}

    public function __invoke(IlluminateLogger $logger): void
    {
        $monolog = $logger->getLogger();

        if (! $monolog instanceof MonologLogger) {
            return;
        }

        $configuredKeys = $this->config->get('error-reporting.sensitive_keys', []);
        $sensitiveKeys = [];

        if (is_array($configuredKeys)) {
            foreach ($configuredKeys as $key) {
                if (is_string($key)) {
                    $sensitiveKeys[] = $key;
                }
            }
        }

        foreach ($monolog->getHandlers() as $handler) {
            if (! $handler instanceof FormattableHandlerInterface) {
                continue;
            }

            $formatter = $handler->getFormatter();

            if ($formatter instanceof RedactingFormatter) {
                continue;
            }

            $handler->setFormatter(new RedactingFormatter($formatter, $sensitiveKeys));
        }
    }
}
