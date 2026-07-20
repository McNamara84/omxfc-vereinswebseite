<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\MaddraxikonContributionStatus;
use App\Enums\Role;
use App\Models\MaddraxikonContribution;
use App\Models\Membership;
use App\Services\LockedMembersTeamMemberships;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use App\Services\Maddraxikon\MaddraxikonApiRequestGuard;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use App\Services\MembersTeamMembershipLock;
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
 * This executable is intentionally kept outside the PHPUnit process. It gives
 * the concurrency test two independent Laravel database connections without
 * relying on timing sleeps or on uncommitted test-process fixtures.
 */
final class MariaDbMembershipConcurrencyWorker extends MaddraxikonApiClient
{
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
                    'sha1' => 'concurrency-test-sha1',
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
function emitWorkerResult(array $payload): void
{
    fwrite(
        STDOUT,
        json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL
    );
    fflush(STDOUT);
}

/**
 * Refuse to touch anything except the dedicated MariaDB test database even
 * when this helper is accidentally invoked outside PHPUnit.
 */
function assertSafeMariaDbTestDatabase(): void
{
    $connection = (string) config('database.default');
    $driver = (string) config("database.connections.{$connection}.driver");
    $database = (string) config("database.connections.{$connection}.database");

    if (
        ! app()->environment('testing')
        || ! in_array($driver, ['mysql', 'mariadb'], true)
        || ! str_contains($database, '_test')
    ) {
        throw new RuntimeException(sprintf(
            'Unsichere Worker-Datenbank verweigert: %s/%s (%s).',
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
            'Der Nebenlaeufigkeits-Worker darf nur gegen MariaDB laufen.'
        );
    }
}

try {
    assertSafeMariaDbTestDatabase();

    config([
        'logging.default' => 'null',
        'maddraxikon.features.awards_enabled' => true,
        'maddraxikon.daily_point_cap' => 10,
        'maddraxikon.minimum_article_bytes' => 500,
        'maddraxikon.article_namespace' => 0,
        'maddraxikon.evaluation.source_batch_size' => 10,
        'maddraxikon.evaluation.api_batch_size' => 10,
    ]);

    $mode = (string) ($argv[1] ?? '');
    $userId = (int) ($argv[2] ?? 0);
    $contributionId = (int) ($argv[3] ?? 0);

    if ($userId < 1) {
        throw new RuntimeException('Eine gueltige Nutzer-ID ist erforderlich.');
    }

    if ($mode === 'hold-demotion') {
        app(MembersTeamMembershipLock::class)->run(
            [$userId],
            function (
                LockedMembersTeamMemberships $memberships
            ) use ($userId): void {
                if (! $memberships->isActiveMember($userId)) {
                    throw new RuntimeException(
                        'Der Testnutzer ist vor der Demotion nicht aktiv.'
                    );
                }

                $updated = Membership::query()
                    ->where('team_id', $memberships->team->id)
                    ->where('user_id', $userId)
                    ->update([
                        'role' => Role::Anwaerter->value,
                        'updated_at' => now(),
                    ]);

                if ($updated !== 1) {
                    throw new RuntimeException(
                        'Die Mitgliederrolle konnte nicht demotiert werden.'
                    );
                }

                emitWorkerResult([
                    'ok' => true,
                    'event' => 'demotion_locked',
                ]);

                $command = trim((string) fgets(STDIN));

                if ($command !== 'COMMIT') {
                    throw new RuntimeException(
                        'Die gesperrte Demotion wurde abgebrochen.'
                    );
                }
            },
            attempts: 1
        );

        emitWorkerResult([
            'ok' => true,
            'event' => 'demotion_committed',
        ]);

        exit(0);
    }

    if ($mode === 'evaluate') {
        if ($contributionId < 1) {
            throw new RuntimeException(
                'Eine gueltige Beitrags-ID ist erforderlich.'
            );
        }

        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        app()->instance(
            MaddraxikonApiClient::class,
            new MariaDbMembershipConcurrencyWorker(
                app(MaddraxikonApiRequestGuard::class),
            ),
        );

        $written = app(MaddraxikonRewardService::class)->evaluate(
            force: true,
            onlyContributionId: $contributionId
        );
        $contribution = MaddraxikonContribution::query()
            ->findOrFail($contributionId);

        emitWorkerResult([
            'ok' => true,
            'event' => 'evaluation_finished',
            'written' => $written,
            'status' => $contribution->status
                instanceof MaddraxikonContributionStatus
                    ? $contribution->status->value
                    : (string) $contribution->status,
            'status_reason' => $contribution->status_reason,
            'evaluation_attempts' => $contribution->evaluation_attempts,
            'last_evaluation_error' => $contribution->last_evaluation_error,
        ]);

        exit(0);
    }

    throw new RuntimeException("Unbekannter Worker-Modus: {$mode}");
} catch (Throwable $exception) {
    emitWorkerResult([
        'ok' => false,
        'exception' => class_basename($exception),
        'message' => $exception->getMessage(),
    ]);

    fclose(STDIN);
    exit(1);
}
