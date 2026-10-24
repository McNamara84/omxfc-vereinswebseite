<?php

namespace App\Services\Maddraxikon;

use App\Models\MaddraxikonStreakRun;
use App\Models\MaddraxikonSyncState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

final class MaddraxikonStreakSettings
{
    public function start(): ?CarbonImmutable
    {
        // Once activated, every consumer must use the same immutable rollout boundary.
        $epoch = MaddraxikonStreakRun::query()
            ->where('wiki_key', config('maddraxikon.wiki_key'))
            ->value('starts_at_epoch');
        if ($epoch !== null) {
            return CarbonImmutable::createFromTimestampUTC((int) $epoch);
        }

        $value = config('maddraxikon.streaks.starts_at');
        if (! is_string($value) || $value === '') {
            return null;
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

    /** Repeatable bootstrap for deployment and the first write after a later activation. */
    public function activate(): ?MaddraxikonStreakRun
    {
        if (! config('maddraxikon.streaks.enabled')) {
            return null;
        }

        return DB::transaction(function (): MaddraxikonStreakRun {
            // Match the import/evaluation lock order and serialize concurrent first activations.
            // Locking reads also see a preceding worker's commit under REPEATABLE READ.
            $wiki = (string) config('maddraxikon.wiki_key');
            $sync = MaddraxikonSyncState::query()->where('wiki_key', $wiki)->lockForUpdate()->first();
            if (! $sync) {
                $sync = MaddraxikonSyncState::query()->create(['wiki_key' => $wiki]);
            }
            $now = CarbonImmutable::now('UTC')->startOfSecond();
            $run = MaddraxikonStreakRun::query()->where('wiki_key', $wiki)->lockForUpdate()->first();
            if (! $run) {
                $start = $this->start() ?? $now;
                $run = MaddraxikonStreakRun::query()->create(['wiki_key' => $wiki, 'starts_at_epoch' => $start->timestamp]);
            }

            if ($sync->watermark_at === null && $sync->recovery_required_at === null) {
                // Queue the missing interval for import; do not claim it has already succeeded.
                // A future streak start must not postpone the regular contribution import.
                $initial = $sync->initial_watermark_at ?? CarbonImmutable::createFromTimestampUTC(min($run->starts_at_epoch, $now->timestamp));
                $sync->update(['watermark_at' => $initial, 'initial_watermark_at' => $initial]);
            }

            return $run;
        }, 3);
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
