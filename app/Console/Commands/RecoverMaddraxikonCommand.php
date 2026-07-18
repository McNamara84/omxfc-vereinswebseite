<?php

namespace App\Console\Commands;

use App\Models\MaddraxikonSyncState;
use App\Services\Maddraxikon\MaddraxikonContributionImporter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class RecoverMaddraxikonCommand extends Command
{
    protected $signature = 'maddraxikon:recover
        {--from= : Beginn des abgeschlossenen Recovery-Fensters (ISO-8601)}
        {--until= : Ende des abgeschlossenen Recovery-Fensters (ISO-8601)}
        {--yes : Sicherheitsabfrage überspringen}';

    protected $description = 'Importiert ein begrenztes Ausfallfenster über MediaWiki list=usercontribs.';

    public function handle(MaddraxikonContributionImporter $importer): int
    {
        $wikiKey = (string) config('maddraxikon.wiki_key', 'maddraxikon-de');
        $state = MaddraxikonSyncState::query()
            ->where('wiki_key', $wikiKey)
            ->first();

        if (! $state?->watermark_at) {
            $this->error('Es existiert noch keine Go-live-Watermark für dieses Wiki.');

            return self::FAILURE;
        }

        try {
            $from = $this->resolveTimestamp(
                'from',
                $state->recovery_from_at
            );
            $until = $this->resolveTimestamp(
                'until',
                $state->recovery_until_at
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $from || ! $until) {
            $this->error('Für den offenen Recovery-Alarm fehlt ein Zeitfenster.');

            return self::FAILURE;
        }

        if ($state->recovery_required_at === null) {
            $this->error('Recovery ist nur bei einem offenen Recovery-Alarm zulässig.');

            return self::FAILURE;
        }

        $initialWatermark = CarbonImmutable::instance(
            $state->initial_watermark_at ?? $state->watermark_at
        );
        $maximumDays = max(
            1,
            (int) config('maddraxikon.sync.recovery_max_window_days', 90)
        );

        if ($from->lt($initialWatermark)) {
            $this->error(sprintf(
                'Das Recovery-Fenster darf nicht vor der Go-live-Watermark %s beginnen.',
                $initialWatermark->utc()->toIso8601String()
            ));

            return self::FAILURE;
        }

        if (! $from->lt($until)) {
            $this->error('--from muss zeitlich vor --until liegen.');

            return self::FAILURE;
        }

        if ($until->isFuture()) {
            $this->error('--until darf nicht in der Zukunft liegen.');

            return self::FAILURE;
        }

        if ($from->diffInSeconds($until) > $maximumDays * 86_400) {
            $this->error(sprintf(
                'Ein Recovery-Lauf darf höchstens %d Tage umfassen. Bitte das Fenster lückenlos aufteilen.',
                $maximumDays
            ));

            return self::FAILURE;
        }

        if (! $state->recovery_from_at || ! $from->equalTo(
            CarbonImmutable::instance($state->recovery_from_at)
        )) {
            $this->error(
                'Das Fenster muss exakt am Anfang der offenen Lücke beginnen.'
            );

            return self::FAILURE;
        }

        if (
            ! $state->recovery_until_at
            || $until->gt(CarbonImmutable::instance($state->recovery_until_at))
        ) {
            $this->error(
                'Das Fenster darf nicht über das Ende der offenen Lücke hinausgehen.'
            );

            return self::FAILURE;
        }

        $this->warn(
            'Recovery-Beitraege werden wegen des nicht rekonstruierbaren Bot-Merkmals '
            .'nur revisionsgebunden archiviert und nicht automatisch mit Baxx belohnt.'
        );

        $question = sprintf(
            'Recovery für %s von %s bis %s wirklich ausführen?',
            $wikiKey,
            $from->utc()->toIso8601String(),
            $until->utc()->toIso8601String()
        );

        if (! $this->option('yes') && ! $this->confirm($question, false)) {
            $this->warn('Recovery abgebrochen.');

            return self::FAILURE;
        }

        try {
            $imported = $importer->recover($from, $until);
        } catch (Throwable $exception) {
            $this->error('Recovery fehlgeschlagen: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Recovery abgeschlossen: %d Maddraxikon-Beiträge importiert (%s bis %s).',
            $imported,
            $from->utc()->toIso8601String(),
            $until->utc()->toIso8601String()
        ));

        $state->refresh();

        if ($state->recovery_required_at) {
            $this->warn(sprintf(
                'Recovery bleibt offen ab %s.',
                $state->recovery_from_at?->utc()->toIso8601String() ?? 'unbekannt'
            ));
        }

        return self::SUCCESS;
    }

    private function resolveTimestamp(
        string $option,
        mixed $fallback
    ): ?CarbonImmutable {
        $value = $this->option($option);

        if (! is_string($value) || trim($value) === '') {
            return $fallback
                ? CarbonImmutable::instance($fallback)
                : null;
        }

        $value = trim($value);

        if (! preg_match(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D',
            $value
        )) {
            throw new \InvalidArgumentException(
                sprintf(
                    '--%s muss ISO-8601 mit Sekunden und Z oder explizitem Offset sein.',
                    $option
                )
            );
        }

        try {
            $normalized = str_ends_with($value, 'Z')
                ? substr($value, 0, -1).'+00:00'
                : $value;
            $timestamp = CarbonImmutable::createFromFormat(
                '!Y-m-d\TH:i:sP',
                $normalized
            );
            $errors = CarbonImmutable::getLastErrors();

            if (
                ! $timestamp
                || (
                    is_array($errors)
                    && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)
                )
            ) {
                throw new \InvalidArgumentException;
            }

            return $timestamp->utc();
        } catch (Throwable) {
            throw new \InvalidArgumentException(
                sprintf('--%s ist kein gültiger ISO-8601-Zeitstempel.', $option)
            );
        }
    }
}
