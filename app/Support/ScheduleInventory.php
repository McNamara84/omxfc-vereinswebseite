<?php

declare(strict_types=1);

namespace App\Support;

use UnexpectedValueException;

final class ScheduleInventory
{
    private const array REQUIRED_TASKS = [
        'member-map:refresh',
        'rpg:process-combats',
        'polls:archive-ended',
        'database-maintenance:cleanup',
        'maddraxikon:prune-audit',
        'maddraxikon:heartbeat',
        'maddraxikon:sync-job',
        'maddraxikon:evaluate-job',
        'maddraxikon:review-ratings-sync-job',
        'cover-ratings:sync-job',
    ];

    /** @return array<string, bool> Task name => on_one_server */
    public static function verify(string $json): array
    {
        $events = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($events) || ! array_is_list($events)) {
            throw new UnexpectedValueException('Der Scheduler muss eine JSON-Liste liefern.');
        }

        $registered = [];

        foreach ($events as $event) {
            if (! is_array($event)
                || ! is_string($event['command'] ?? null)
                || ! is_string($event['expression'] ?? null)
                || $event['expression'] === ''
                || ! is_bool($event['on_one_server'] ?? null)) {
                throw new UnexpectedValueException('Unvollständiger Scheduler-Eintrag.');
            }

            if (($event['timezone'] ?? null) !== 'Europe/Berlin') {
                throw new UnexpectedValueException('Der Scheduler muss Europe/Berlin verwenden.');
            }

            foreach (self::REQUIRED_TASKS as $task) {
                if ($event['command'] === $task
                    || preg_match('/^php artisan '.preg_quote($task, '/').'(?:\s|$)/', $event['command']) === 1) {
                    $registered[$task] = $event['on_one_server'];
                }
            }
        }

        $missing = array_diff(self::REQUIRED_TASKS, array_keys($registered));

        if ($missing !== []) {
            throw new UnexpectedValueException('Geplante Aufgaben fehlen: '.implode(', ', $missing));
        }

        return $registered;
    }
}
