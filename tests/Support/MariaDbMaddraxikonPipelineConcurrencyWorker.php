<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\MaddraxikonContributionStatus;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use App\Services\Maddraxikon\MaddraxikonContributionImporter;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

use function array_map;
use function class_basename;
use function config;
use function fclose;
use function fflush;
use function fgets;
use function fwrite;
use function in_array;
use function json_encode;
use function sprintf;
use function str_contains;
use function trim;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/**
 * The API double deliberately runs in a separate PHP process. For imports it
 * exposes a barrier after the importer has read the sync state, but before it
 * persists the shared RecentChanges row.
 */
final class MariaDbMaddraxikonPipelineConcurrencyWorker extends MaddraxikonApiClient
{
    public function __construct(
        private readonly string $mode,
        private readonly int $userId,
        private readonly int $externalId,
    ) {}

    public function recentChanges(
        CarbonInterface $from,
        CarbonInterface $until
    ): array {
        if ($this->mode !== 'import') {
            throw new RuntimeException(
                'RecentChanges wurde im falschen Worker-Modus aufgerufen.'
            );
        }

        $link = MaddraxikonAccountLink::query()
            ->where('user_id', $this->userId)
            ->active()
            ->firstOrFail();

        emitPipelineWorkerResult([
            'ok' => true,
            'event' => 'import_api_ready',
            'from' => $from->clone()->utc()->toIso8601String(),
            'until' => $until->clone()->utc()->toIso8601String(),
        ]);

        if (trim((string) fgets(STDIN)) !== 'PERSIST') {
            throw new RuntimeException(
                'Der konkurrierende Import wurde vor dem Persistieren abgebrochen.'
            );
        }

        return [[
            'type' => 'new',
            'ns' => 0,
            'title' => 'Paralleler Import',
            'pageid' => $this->externalId + 10_000,
            'revid' => $this->externalId,
            'old_revid' => 0,
            'rcid' => $this->externalId + 20_000,
            'user' => $link->wiki_username,
            'userid' => $link->wiki_user_id,
            'oldlen' => 0,
            'newlen' => 750,
            'timestamp' => $until
                ->clone()
                ->subSecond()
                ->utc()
                ->format('Y-m-d\TH:i:s\Z'),
            'tags' => [],
        ]];
    }

    public function revisionDetails(array $revisionIds): array
    {
        return MaddraxikonContribution::query()
            ->whereIn('revision_id', array_map('intval', $revisionIds))
            ->get()
            ->mapWithKeys(static fn (
                MaddraxikonContribution $contribution
            ): array => [
                $contribution->revision_id => [
                    'exists' => true,
                    'revision_id' => $contribution->revision_id,
                    'page_id' => $contribution->page_id,
                    'namespace_id' => $contribution->namespace_id,
                    'user_id' => $contribution->wiki_user_id,
                    'user_hidden' => false,
                    'suppressed' => false,
                    'sha1' => 'pipeline-concurrency-sha1',
                    'sha1_hidden' => false,
                    'text_hidden' => false,
                    'size' => $contribution->new_size,
                    'tags' => [],
                ],
            ])
            ->all();
    }

    public function pageDetails(array $pageIds): array
    {
        return MaddraxikonContribution::query()
            ->whereIn('page_id', array_map('intval', $pageIds))
            ->get()
            ->mapWithKeys(static fn (
                MaddraxikonContribution $contribution
            ): array => [
                $contribution->page_id => [
                    'exists' => true,
                    'page_id' => $contribution->page_id,
                    'namespace_id' => $contribution->namespace_id,
                    'title' => $contribution->page_title,
                    'size' => max(1_000, (int) $contribution->new_size),
                    'redirect' => false,
                ],
            ])
            ->all();
    }
}

/**
 * @param  array<string, mixed>  $payload
 */
function emitPipelineWorkerResult(array $payload): void
{
    fwrite(
        STDOUT,
        json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL
    );
    fflush(STDOUT);
}

/**
 * Refuse to run if the inherited PHPUnit environment does not point to the
 * dedicated MariaDB test database.
 */
function assertSafePipelineMariaDbTestDatabase(): void
{
    $connection = (string) config('database.default');
    $driver = (string) config("database.connections.{$connection}.driver");
    $database = (string) config("database.connections.{$connection}.database");

    if (
        ! app()->environment('testing')
        || ! in_array($driver, ['mysql', 'mariadb'], true)
        || $database !== 'omxfc_maddraxikon_test'
    ) {
        throw new RuntimeException(sprintf(
            'Unsichere Pipeline-Worker-Datenbank verweigert: %s/%s (%s).',
            $driver,
            $database,
            app()->environment()
        ));
    }

    $version = (string) (DB::selectOne(
        'SELECT VERSION() AS database_version'
    )->database_version ?? '');

    if (! str_contains($version, 'MariaDB')) {
        throw new RuntimeException(
            'Der Pipeline-Worker darf ausschließlich gegen MariaDB laufen.'
        );
    }
}

try {
    assertSafePipelineMariaDbTestDatabase();

    config([
        'logging.default' => 'null',
        'maddraxikon.features.sync_enabled' => true,
        'maddraxikon.features.awards_enabled' => true,
        'maddraxikon.wiki_key' => 'maddraxikon-de',
        'maddraxikon.allowed_namespaces' => [0],
        'maddraxikon.sync.overlap_minutes' => 10,
        'maddraxikon.sync.max_window_minutes' => 360,
        'maddraxikon.sync.recent_changes_retention_days' => 30,
        'maddraxikon.evaluation_delay_hours' => 24,
        'maddraxikon.daily_point_cap' => 10,
        'maddraxikon.minimum_article_bytes' => 500,
        'maddraxikon.article_namespace' => 0,
        'maddraxikon.evaluation.source_batch_size' => 10,
        'maddraxikon.evaluation.api_batch_size' => 10,
    ]);

    DB::statement('SET SESSION innodb_lock_wait_timeout = 10');

    $mode = (string) ($argv[1] ?? '');
    $userId = (int) ($argv[2] ?? 0);
    $externalId = (int) ($argv[3] ?? 0);

    if ($userId < 1 || $externalId < 1) {
        throw new RuntimeException(
            'Gültige Nutzer- und externe Quell-IDs sind erforderlich.'
        );
    }

    $api = new MariaDbMaddraxikonPipelineConcurrencyWorker(
        $mode,
        $userId,
        $externalId
    );
    app()->instance(MaddraxikonApiClient::class, $api);

    if ($mode === 'evaluate') {
        emitPipelineWorkerResult([
            'ok' => true,
            'event' => 'evaluator_ready',
        ]);

        if (trim((string) fgets(STDIN)) !== 'EVALUATE') {
            throw new RuntimeException(
                'Die konkurrierende Auswertung wurde vor dem Start abgebrochen.'
            );
        }

        $written = app(MaddraxikonRewardService::class)->evaluate(
            force: true,
            onlyContributionId: $externalId
        );
        $contribution = MaddraxikonContribution::query()
            ->findOrFail($externalId);

        emitPipelineWorkerResult([
            'ok' => true,
            'event' => 'evaluation_finished',
            'written' => $written,
            'status' => $contribution->status
                instanceof MaddraxikonContributionStatus
                    ? $contribution->status->value
                    : (string) $contribution->status,
        ]);

        exit(0);
    }

    if ($mode === 'import') {
        $written = app(MaddraxikonContributionImporter::class)->sync(
            force: true
        );

        emitPipelineWorkerResult([
            'ok' => true,
            'event' => 'import_finished',
            'written' => $written,
        ]);

        exit(0);
    }

    throw new RuntimeException("Unbekannter Worker-Modus: {$mode}");
} catch (Throwable $exception) {
    emitPipelineWorkerResult([
        'ok' => false,
        'exception' => class_basename($exception),
        'message' => $exception->getMessage(),
    ]);

    fclose(STDIN);
    exit(1);
}
