<?php

namespace App\Services\Maddraxikon;

use App\Models\MaddraxikonStreakRun;
use Carbon\CarbonImmutable;
use LogicException;

final class MaddraxikonStreakSettings
{
    public function start(): ?CarbonImmutable
    {
        $value = config('maddraxikon.streaks.starts_at');
        if (! is_string($value) || $value === '') {
            $epoch = MaddraxikonStreakRun::query()
                ->where('wiki_key', config('maddraxikon.wiki_key'))
                ->value('starts_at_epoch');

            return $epoch === null ? null : CarbonImmutable::createFromTimestampUTC((int) $epoch);
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new LogicException('Der Serienstart benötigt einen ISO-Zeitpunkt mit Zeitzone.');
        }

        $date = CarbonImmutable::parse($value);
        if ($date->format('Y-m-d\TH:i:sP') !== str_replace('Z', '+00:00', $value)) {
            throw new LogicException('Der Serienstart muss ein gültiges Datum mit gültiger Uhrzeit sein.');
        }

        return $date->utc();
    }

    public function enabled(): bool
    {
        return (bool) config('maddraxikon.streaks.enabled') && $this->start() !== null;
    }

    public function pinStart(): MaddraxikonStreakRun
    {
        $start = $this->start() ?? throw new LogicException('Der Serienstart fehlt.');
        $run = MaddraxikonStreakRun::query()->firstOrCreate(
            ['wiki_key' => config('maddraxikon.wiki_key')],
            ['starts_at_epoch' => $start->timestamp],
        );
        if ($run->starts_at_epoch !== $start->timestamp) {
            throw new LogicException('Der bereits verwendete Serienstart darf nicht verschoben werden.');
        }

        return $run;
    }

    public function timezone(): string
    {
        return (string) config('maddraxikon.timezone', 'Europe/Berlin');
    }
}
