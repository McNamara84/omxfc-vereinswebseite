<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneMaddraxikonAuditCommand extends Command
{
    protected $signature = 'maddraxikon:prune-audit
        {--pretend : Nur die Anzahl abgelaufener Datensaetze anzeigen}';

    protected $description = 'Loescht abgelaufene Maddraxikon-Zuordnungskorrekturen nach der Aufbewahrungsfrist.';

    public function handle(): int
    {
        $retentionDays = max(
            365,
            (int) config('maddraxikon.privacy.correction_audit_retention_days', 3650)
        );
        $cutoff = CarbonImmutable::now()
            ->subDays($retentionDays)
            ->setTimezone((string) config('app.timezone', 'UTC'))
            ->format('Y-m-d H:i:s');
        $query = DB::table('maddraxikon_account_link_corrections')
            ->where('corrected_at', '<', $cutoff);
        $expired = (clone $query)->count();

        if ($this->option('pretend')) {
            $this->info(sprintf(
                '%d abgelaufene Maddraxikon-Korrekturprotokolle (Stichtag: %s).',
                $expired,
                $cutoff
            ));

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info(sprintf(
            '%d abgelaufene Maddraxikon-Korrekturprotokolle geloescht.',
            $deleted
        ));

        return self::SUCCESS;
    }
}
