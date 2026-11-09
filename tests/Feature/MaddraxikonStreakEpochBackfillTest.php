<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MaddraxikonContribution;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonStreakEpochBackfillTestFixtures::class);
dataset('MaddraxikonStreakEpochBackfillTest.legacyInstants', static fn (): array => ['UTC' => ['UTC', '2026-10-01 12:00:00', '2026-10-01T12:00:00+00:00'], 'Berlin summer' => ['Europe/Berlin', '2026-10-01 12:00:00', '2026-10-01T10:00:00+00:00'], 'Berlin spring transition' => ['Europe/Berlin', '2026-03-29 03:15:00', '2026-03-29T01:15:00+00:00'], 'Berlin autumn transition' => ['Europe/Berlin', '2026-10-25 03:15:00', '2026-10-25T02:15:00+00:00'], 'ambiguous autumn hour keeps existing fallback' => ['Europe/Berlin', '2026-10-25 02:30:00', null]]);
test('backfill preserves the existing legacy timezone interpretation', function (string $timezone, string $localTime, ?string $utc): void {
    config(['app.timezone' => $timezone]);
    $source = MaddraxikonContribution::factory()->create();
    DB::table('maddraxikon_contributions')->where('id', $source->id)->update(['occurred_at_epoch' => null, 'occurred_at' => $localTime]);
    $source->refresh();
    $expected = $source->getAttributes();
    $instant = $source->occurredAtUtc();
    if ($utc !== null) {
        expect($instant->toIso8601String())->toBe($utc);
    }
    $expected['occurred_at_epoch'] = $instant->timestamp;
    $migration = $this->migration();
    $migration->up();
    expect($source->fresh()->getAttributes())->toBe($expected);
    $migration->down();
    $migration->up();
    expect($source->fresh()->getAttributes())->toBe($expected);
})->with('MaddraxikonStreakEpochBackfillTest.legacyInstants');
test('backfill processes multiple batches without rewriting known epochs or other fields', function (): void {
    config(['app.timezone' => 'Europe/Berlin']);
    $known = MaddraxikonContribution::factory()->create(['occurred_at' => '2026-10-01T12:00:00Z']);
    $before = $known->fresh()->getAttributes();
    $legacy = $before;
    unset($legacy['id']);
    $legacy['occurred_at'] = '2026-10-01 12:00:00';
    $legacy['occurred_at_epoch'] = null;
    $rows = [];
    for ($i = 0; $i < 501; $i++) {
        $rows[] = [...$legacy, 'rc_id' => 3000000000 + $i, 'revision_id' => 3000000000 + $i];
    }
    DB::table('maddraxikon_contributions')->insert($rows);
    $expected = MaddraxikonContribution::query()->whereNull('occurred_at_epoch')->firstOrFail()->occurredAtUtc()->timestamp;
    $this->migration()->up();
    expect(MaddraxikonContribution::query()->whereNull('occurred_at_epoch')->count())->toBe(0)
        ->and(MaddraxikonContribution::query()->where('occurred_at_epoch', $expected)->count())->toBe(501)
        ->and($known->fresh()->getAttributes())->toBe($before);
    $last = MaddraxikonContribution::query()->where('revision_id', 3000000500)->sole()->getAttributes();
    expect(array_diff_key($last, ['id' => true]))->toBe(array_replace($legacy, ['occurred_at_epoch' => $expected, 'rc_id' => 3000000500, 'revision_id' => 3000000500]));
});
