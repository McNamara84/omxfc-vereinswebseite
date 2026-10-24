<?php

namespace Tests\Feature;

use App\Models\MaddraxikonContribution;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaddraxikonStreakEpochBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_24_140000_backfill_maddraxikon_contribution_epochs.php');
    }

    #[DataProvider('legacyInstants')]
    public function test_backfill_preserves_the_existing_legacy_timezone_interpretation(string $timezone, string $localTime, ?string $utc): void
    {
        config(['app.timezone' => $timezone]);
        $source = MaddraxikonContribution::factory()->create();
        DB::table('maddraxikon_contributions')->where('id', $source->id)
            ->update(['occurred_at_epoch' => null, 'occurred_at' => $localTime]);
        $source->refresh();
        $expected = $source->getAttributes();
        $instant = $source->occurredAtUtc();
        if ($utc !== null) {
            $this->assertSame($utc, $instant->toIso8601String());
        }
        $expected['occurred_at_epoch'] = $instant->timestamp;
        $migration = $this->migration();
        $migration->up();
        $this->assertSame($expected, $source->fresh()->getAttributes());
        $migration->down();
        $migration->up();
        $this->assertSame($expected, $source->fresh()->getAttributes());
    }

    public static function legacyInstants(): array
    {
        return [
            'UTC' => ['UTC', '2026-10-01 12:00:00', '2026-10-01T12:00:00+00:00'],
            'Berlin summer' => ['Europe/Berlin', '2026-10-01 12:00:00', '2026-10-01T10:00:00+00:00'],
            'Berlin spring transition' => ['Europe/Berlin', '2026-03-29 03:15:00', '2026-03-29T01:15:00+00:00'],
            'Berlin autumn transition' => ['Europe/Berlin', '2026-10-25 03:15:00', '2026-10-25T02:15:00+00:00'],
            'ambiguous autumn hour keeps existing fallback' => ['Europe/Berlin', '2026-10-25 02:30:00', null],
        ];
    }

    public function test_backfill_processes_multiple_batches_without_rewriting_known_epochs_or_other_fields(): void
    {
        config(['app.timezone' => 'Europe/Berlin']);
        $known = MaddraxikonContribution::factory()->create(['occurred_at' => '2026-10-01T12:00:00Z']);
        $before = $known->fresh()->getAttributes();
        $legacy = $before;
        unset($legacy['id']);
        $legacy['occurred_at'] = '2026-10-01 12:00:00';
        $legacy['occurred_at_epoch'] = null;
        $rows = [];
        for ($i = 0; $i < 501; $i++) {
            $rows[] = [...$legacy, 'rc_id' => 3_000_000_000 + $i, 'revision_id' => 3_000_000_000 + $i];
        }
        DB::table('maddraxikon_contributions')->insert($rows);
        $expected = MaddraxikonContribution::query()->whereNull('occurred_at_epoch')->firstOrFail()->occurredAtUtc()->timestamp;
        $this->migration()->up();

        $this->assertSame(0, MaddraxikonContribution::query()->whereNull('occurred_at_epoch')->count());
        $this->assertSame(501, MaddraxikonContribution::query()->where('occurred_at_epoch', $expected)->count());
        $this->assertSame($before, $known->fresh()->getAttributes());
        $last = MaddraxikonContribution::query()->where('revision_id', 3_000_000_500)->sole()->getAttributes();
        $this->assertSame(array_replace($legacy, ['occurred_at_epoch' => $expected, 'rc_id' => 3_000_000_500, 'revision_id' => 3_000_000_500]), array_diff_key($last, ['id' => true]));
    }
}
