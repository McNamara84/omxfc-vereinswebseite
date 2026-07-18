<?php

namespace App\Console\Commands;

use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonRewardEventStatus;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardEvent;
use App\Models\MaddraxikonSyncState;
use App\Services\Maddraxikon\MaddraxikonMonitoring;
use App\Services\Maddraxikon\MaddraxikonNamespaceHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MaddraxikonStatusCommand extends Command
{
    protected $signature = 'maddraxikon:status
        {--skip-api : Namensräume nicht live über die Maddraxikon-API prüfen}';

    protected $description = 'Zeigt Schalter, Synchronisationszustand und Namespace-Integrität an.';

    public function handle(
        MaddraxikonNamespaceHealthService $namespaceHealth,
        MaddraxikonMonitoring $monitoring,
    ): int {
        $wikiKey = (string) config('maddraxikon.wiki_key', 'maddraxikon-de');
        $state = MaddraxikonSyncState::query()
            ->where('wiki_key', $wikiKey)
            ->first();
        $pending = MaddraxikonContribution::query()
            ->where('wiki_key', $wikiKey)
            ->where('status', MaddraxikonContributionStatus::Pending)
            ->count();
        $overdue = MaddraxikonContribution::query()
            ->where('wiki_key', $wikiKey)
            ->where('status', MaddraxikonContributionStatus::Pending)
            ->where(function ($query): void {
                $query
                    ->where(
                        'eligible_after_epoch',
                        '<=',
                        now()->getTimestamp()
                    )
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNull('eligible_after_epoch')
                            ->where('eligible_after', '<=', now());
                    });
            })
            ->count();
        $pendingStaleHours = max(
            1,
            (int) config('maddraxikon.monitoring.pending_stale_hours', 26)
        );
        $stalePending = MaddraxikonContribution::query()
            ->where('wiki_key', $wikiKey)
            ->where('status', MaddraxikonContributionStatus::Pending)
            ->where(function ($query) use ($pendingStaleHours): void {
                $threshold = now()->subHours($pendingStaleHours);
                $query
                    ->where(
                        'occurred_at_epoch',
                        '<=',
                        $threshold->getTimestamp()
                    )
                    ->orWhere(function ($query) use ($threshold): void {
                        $query->whereNull('occurred_at_epoch')
                            ->where('occurred_at', '<=', $threshold);
                    });
            })
            ->count();
        $technicalFailures = MaddraxikonContribution::query()
            ->where('wiki_key', $wikiKey)
            ->where('status', MaddraxikonContributionStatus::Pending)
            ->whereNotNull('last_evaluation_error_at')
            ->count();
        $rewardCounts = MaddraxikonRewardEvent::query()
            ->where('wiki_key', $wikiKey)
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count);
        $awarded = $rewardCounts->get(
            MaddraxikonRewardEventStatus::Awarded->value,
            0
        );
        $rejected = $rewardCounts->get(
            MaddraxikonRewardEventStatus::Rejected->value,
            0
        );
        $noAward = $rewardCounts->get(
            MaddraxikonRewardEventStatus::EvaluatedNoAward->value,
            0
        );
        $reversed = $rewardCounts->get(
            MaddraxikonRewardEventStatus::Reversed->value,
            0
        );
        $cap = MaddraxikonRewardEvent::query()
            ->where('wiki_key', $wikiKey)
            ->where('capped_points', '>', 0)
            ->selectRaw(
                'COUNT(*) as event_count, COALESCE(SUM(capped_points), 0) as point_count'
            )
            ->first();
        $topReasons = MaddraxikonRewardEvent::query()
            ->where('wiki_key', $wikiKey)
            ->whereNotNull('status_reason')
            ->select('status_reason', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status_reason')
            ->orderByDesc('aggregate')
            ->limit(5)
            ->get()
            ->map(
                fn (MaddraxikonRewardEvent $event): string => (
                    $event->status_reason.': '.(int) $event->aggregate
                )
            )
            ->implode(', ');
        $heartbeat = $monitoring->schedulerHeartbeat();
        $heartbeatAgeMinutes = $heartbeat
            ? (int) $heartbeat->diffInMinutes(now('UTC'), true)
            : null;
        $lastSuccessAgeMinutes = $state?->last_succeeded_at
            ? (int) $state->last_succeeded_at->diffInMinutes(now(), true)
            : null;
        $queue = $this->queueMetrics();
        $recoveryRequired = $state?->recovery_required_at !== null;
        $recoveryWindow = $recoveryRequired
            ? sprintf(
                '%s bis %s',
                $state->recovery_from_at?->utc()->toIso8601String() ?? 'unbekannt',
                $state->recovery_until_at?->utc()->toIso8601String() ?? 'unbekannt'
            )
            : 'kein offenes Fenster';
        $alarms = $this->operationalAlarms(
            state: $state,
            stalePending: $stalePending,
            technicalFailures: $technicalFailures,
            heartbeatAgeMinutes: $heartbeatAgeMinutes,
            queue: $queue,
        );

        $this->table(['Eigenschaft', 'Wert'], [
            ['Wiki', $wikiKey],
            ['Verknüpfung', $this->switchStatus('linking_enabled')],
            ['Synchronisation', $this->switchStatus('sync_enabled')],
            ['Baxx-Auswertung', $this->switchStatus('awards_enabled')],
            ['Watermark (UTC)', $state?->watermark_at?->utc()->toIso8601String() ?? 'noch nicht gesetzt'],
            ['Go-live-Watermark (UTC)', $state?->initial_watermark_at?->utc()->toIso8601String() ?? 'noch nicht gesetzt'],
            ['Letzter Erfolg (UTC)', $state?->last_succeeded_at?->utc()->toIso8601String() ?? 'noch keiner'],
            ['Alter letzter Erfolg', $lastSuccessAgeMinutes === null ? 'unbekannt' : $lastSuccessAgeMinutes.' Minuten'],
            ['Scheduler-Lebenszeichen (UTC)', $heartbeat?->toIso8601String() ?? 'noch keines'],
            ['Alter Scheduler-Lebenszeichen', $heartbeatAgeMinutes === null ? 'unbekannt' : $heartbeatAgeMinutes.' Minuten'],
            ['Recovery nötig', $recoveryRequired ? 'ja' : 'nein'],
            ['Recovery-Fenster (UTC)', $recoveryWindow],
            ['Fehler in Folge', (string) ($state?->consecutive_failures ?? 0)],
            ['Wartende Beiträge', (string) $pending],
            ['Davon fällig', (string) $overdue],
            ["Älter als {$pendingStaleHours} Stunden", (string) $stalePending],
            ['Technische Auswertungsfehler', (string) $technicalFailures],
            ['Rewards gutgeschrieben/abgelehnt', $awarded.':'.$rejected],
            ['Rewards ohne Auszahlung', (string) $noAward],
            ['Rewards gegengebucht', (string) $reversed],
            ['Hauptgründe', $topReasons === '' ? 'keine' : $topReasons],
            ['Tageslimit-Kappungen', ((int) ($cap?->event_count ?? 0)).' Ereignisse / '.((int) ($cap?->point_count ?? 0)).' Baxx'],
            ['Maddraxikon-Queue-Rückstau', $this->queueMetricLabel($queue['queued'], $queue['connection'])],
            ['Ältester wartender Maddraxikon-Job', $this->queueAgeLabel($queue)],
            ['Fehlgeschlagene Maddraxikon-Jobs', $this->queueMetricLabel($queue['failed'], $queue['connection'])],
        ]);

        if ($state?->last_error) {
            $this->warn('Letzter Sync-Fehler: '.$state->last_error);
        }

        if ($recoveryRequired) {
            $this->error(
                'RecentChanges kann die offene Lücke nicht mehr sicher abdecken. '.
                'Führen Sie maddraxikon:recover bewusst aus; die Watermark wurde nicht übersprungen.'
            );
        }

        foreach ($alarms as $alarm) {
            $this->error('Betriebsalarm: '.$alarm);
        }

        $operationalFailure = $recoveryRequired || $alarms !== [];

        if ((bool) $this->option('skip-api')) {
            $this->comment('Namespace-Prüfung übersprungen.');

            return $operationalFailure ? self::FAILURE : self::SUCCESS;
        }

        try {
            $report = $namespaceHealth->check();
        } catch (Throwable $exception) {
            $this->error('Namespace-Prüfung fehlgeschlagen: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($report['healthy']) {
            $this->info('Namespace-Prüfung erfolgreich.');

            return $operationalFailure ? self::FAILURE : self::SUCCESS;
        }

        foreach ($report['missing'] as $id => $name) {
            $this->error(sprintf(
                'Namensraum %d (%s) fehlt.',
                $id,
                $name === '' ? 'Hauptnamensraum' : $name
            ));
        }

        foreach ($report['mismatched'] as $id => $names) {
            $this->error(sprintf(
                'Namensraum %d: erwartet „%s“, erhalten „%s“.',
                $id,
                $names['expected'],
                $names['actual']
            ));
        }

        return self::FAILURE;
    }

    private function switchStatus(string $key): string
    {
        return config('maddraxikon.features.'.$key, false) ? 'aktiv' : 'inaktiv';
    }

    /**
     * @param  array{queued: ?int, failed: ?int, oldest_age_minutes: ?int, connection: string}  $queue
     * @return list<string>
     */
    private function operationalAlarms(
        ?MaddraxikonSyncState $state,
        int $stalePending,
        int $technicalFailures,
        ?int $heartbeatAgeMinutes,
        array $queue,
    ): array {
        $syncEnabled = (bool) config(
            'maddraxikon.features.sync_enabled',
            false
        );
        $awardsEnabled = (bool) config(
            'maddraxikon.features.awards_enabled',
            false
        );
        $operationsEnabled = $syncEnabled || $awardsEnabled;
        $alarms = [];

        if ($awardsEnabled && ! $syncEnabled) {
            $alarms[] = (
                'Baxx-Auszahlungen sind aktiviert, obwohl der Import deaktiviert ist.'
            );
        }

        if ($syncEnabled) {
            $staleAfter = max(
                1,
                (int) config(
                    'maddraxikon.monitoring.import_stale_minutes',
                    60
                )
            );
            $lastSuccessAge = $state?->last_succeeded_at
                ? (int) $state->last_succeeded_at->diffInMinutes(now(), true)
                : null;

            if ($lastSuccessAge === null) {
                $alarms[] = 'Es wurde noch kein erfolgreicher Import registriert.';
            } elseif ($lastSuccessAge > $staleAfter) {
                $alarms[] = "Der letzte erfolgreiche Import ist {$lastSuccessAge} Minuten alt.";
            }

            $failureLimit = max(
                1,
                (int) config(
                    'maddraxikon.monitoring.consecutive_failure_limit',
                    3
                )
            );

            if (($state?->consecutive_failures ?? 0) >= $failureLimit) {
                $alarms[] = sprintf(
                    'Der Import ist %d-mal in Folge fehlgeschlagen.',
                    $state?->consecutive_failures ?? 0
                );
            }
        }

        if ($operationsEnabled) {
            $schedulerStaleAfter = max(
                1,
                (int) config(
                    'maddraxikon.monitoring.scheduler_stale_minutes',
                    5
                )
            );

            if ($heartbeatAgeMinutes === null) {
                $alarms[] = 'Es wurde noch kein Scheduler-Lebenszeichen registriert.';
            } elseif ($heartbeatAgeMinutes > $schedulerStaleAfter) {
                $alarms[] = "Das Scheduler-Lebenszeichen ist {$heartbeatAgeMinutes} Minuten alt.";
            }

            $backlogLimit = max(
                1,
                (int) config(
                    'maddraxikon.monitoring.queue_backlog_limit',
                    100
                )
            );

            if (
                $queue['connection'] !== 'sync'
                && ($queue['queued'] === null || $queue['failed'] === null)
            ) {
                $alarms[] = 'Die Maddraxikon-Queue-Metrik ist nicht verfügbar.';
            }

            if (($queue['queued'] ?? 0) > $backlogLimit) {
                $alarms[] = sprintf(
                    'In der Queue warten %d Maddraxikon-Jobs.',
                    $queue['queued']
                );
            }

            $oldestLimit = max(
                1,
                (int) config(
                    'maddraxikon.monitoring.queue_oldest_minutes',
                    30
                )
            );

            if (($queue['oldest_age_minutes'] ?? 0) > $oldestLimit) {
                $alarms[] = sprintf(
                    'Der älteste wartende Maddraxikon-Job ist %d Minuten alt.',
                    $queue['oldest_age_minutes']
                );
            }

            if (($queue['failed'] ?? 0) > 0) {
                $alarms[] = sprintf(
                    '%d Maddraxikon-Jobs sind endgültig fehlgeschlagen.',
                    $queue['failed']
                );
            }
        }

        if ($awardsEnabled && $stalePending > 0) {
            $alarms[] = sprintf(
                '%d Beiträge warten länger als vorgesehen auf die Auswertung.',
                $stalePending
            );
        }

        if ($awardsEnabled && $technicalFailures > 0) {
            $alarms[] = sprintf(
                '%d Beiträge haben einen technischen Auswertungsfehler.',
                $technicalFailures
            );
        }

        return $alarms;
    }

    /**
     * @return array{queued: ?int, failed: ?int, oldest_age_minutes: ?int, connection: string}
     */
    private function queueMetrics(): array
    {
        $connection = (string) config('queue.default', 'sync');

        if ($connection !== 'database') {
            return [
                'queued' => null,
                'failed' => null,
                'oldest_age_minutes' => null,
                'connection' => $connection,
            ];
        }

        $jobsTable = (string) config(
            'queue.connections.database.table',
            'jobs'
        );
        $failedTable = (string) config('queue.failed.table', 'failed_jobs');

        try {
            $queued = Schema::hasTable($jobsTable)
                ? DB::table($jobsTable)
                    ->where('payload', 'like', '%Maddraxikon%')
                    ->count()
                : null;
            $oldestCreatedAt = Schema::hasTable($jobsTable)
                ? DB::table($jobsTable)
                    ->where('payload', 'like', '%Maddraxikon%')
                    ->min('created_at')
                : null;
            $failed = Schema::hasTable($failedTable)
                ? DB::table($failedTable)
                    ->where('payload', 'like', '%Maddraxikon%')
                    ->count()
                : null;
        } catch (Throwable) {
            $queued = null;
            $oldestCreatedAt = null;
            $failed = null;
        }

        $oldestAgeMinutes = is_numeric($oldestCreatedAt)
            ? max(0, intdiv(now()->getTimestamp() - (int) $oldestCreatedAt, 60))
            : null;

        return [
            'queued' => $queued === null ? null : (int) $queued,
            'failed' => $failed === null ? null : (int) $failed,
            'oldest_age_minutes' => $oldestAgeMinutes,
            'connection' => $connection,
        ];
    }

    private function queueMetricLabel(
        ?int $value,
        string $connection
    ): string {
        return $value === null
            ? "nicht messbar ({$connection})"
            : (string) $value;
    }

    /**
     * @param  array{queued: ?int, failed: ?int, oldest_age_minutes: ?int, connection: string}  $queue
     */
    private function queueAgeLabel(array $queue): string
    {
        if ($queue['oldest_age_minutes'] !== null) {
            return $queue['oldest_age_minutes'].' Minuten';
        }

        return $queue['queued'] === 0
            ? 'kein wartender Job'
            : 'nicht messbar ('.$queue['connection'].')';
    }
}
