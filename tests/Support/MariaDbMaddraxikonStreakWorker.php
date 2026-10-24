<?php

use App\Models\MaddraxikonContribution;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use App\Services\Maddraxikon\MaddraxikonStreakService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (! app()->environment('testing') || config('database.connections.'.config('database.default').'.database') !== 'omxfc_maddraxikon_test'
        || ! str_contains(DB::selectOne('SELECT VERSION() AS version')->version, 'MariaDB')) {
        throw new RuntimeException('Unsafe streak worker database.');
    }
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-20T12:00:00+02:00'));
    Carbon::setTestNow(CarbonImmutable::now());
    config(['maddraxikon.streaks.enabled' => true, 'maddraxikon.streaks.starts_at' => '2026-10-01T00:00:00+02:00', 'maddraxikon.features.sync_enabled' => true, 'maddraxikon.features.awards_enabled' => true, 'logging.default' => 'null']);
    $invalidRevision = (int) ($argv[1] ?? 0);
    $app->instance(MaddraxikonApiClient::class, new class($invalidRevision) extends MaddraxikonApiClient
    {
        public function __construct(private readonly int $invalidRevision) {}

        public function streakRevisionDetails(array $revisionIds): array
        {
            fwrite(STDOUT, "ready\n");
            fflush(STDOUT);
            if (trim((string) fgets(STDIN)) !== 'go') {
                throw new RuntimeException('Barrier failed');
            }

            return MaddraxikonContribution::query()->whereIn('revision_id', $revisionIds)->get()->mapWithKeys(fn ($source) => [$source->revision_id => [
                'exists' => true, 'user_id' => $source->wiki_user_id, 'page_id' => $source->page_id,
                'tags' => $source->revision_id === $this->invalidRevision ? ['mw-reverted'] : [],
            ]])->all();
        }

        public function streakPageDetails(array $pageIds): array
        {
            return collect($pageIds)->mapWithKeys(fn ($id) => [$id => ['exists' => true, 'namespace_id' => 0, 'redirect' => false]])->all();
        }
    });
    $count = $app->make(MaddraxikonStreakService::class)->run($invalidRevision > 0);
    fwrite(STDOUT, json_encode(['bookings' => $count], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(1);
}
