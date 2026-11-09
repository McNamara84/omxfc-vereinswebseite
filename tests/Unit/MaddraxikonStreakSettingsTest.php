<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MaddraxikonStreakRun;
use App\Services\Maddraxikon\MaddraxikonStreakSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);
dataset('MaddraxikonStreakSettingsTest.invalidStarts', static fn (): array => [['tomorrow'], ['2026-10-01'], ['2026-10-01T12:00:00'], ['2026-02-30T12:00:00Z'], ['2026-10-01T24:00:00Z']]);
dataset('MaddraxikonStreakSettingsTest.changedStarts', static fn (): array => ['later' => ['2026-11-01T00:00:00+01:00'], 'earlier' => ['2026-09-01T00:00:00+02:00'], 'equivalent offset' => ['2026-09-30T22:00:00Z'], 'invalid format' => ['tomorrow'], 'invalid date' => ['2026-02-30T00:00:00Z'], 'empty' => [''], 'missing' => [null]]);
test('invalid or ambiguous rollout time is rejected', function (string $value): void {
    config(['maddraxikon.streaks.starts_at' => $value]);
    expect(fn () => app(MaddraxikonStreakSettings::class)->start())->toThrow(LogicException::class);
})->with('MaddraxikonStreakSettingsTest.invalidStarts');
test('rollout offset is converted to utc', function (): void {
    config(['maddraxikon.streaks.starts_at' => '2026-10-01T00:00:00+02:00']);
    expect(app(MaddraxikonStreakSettings::class)->start()->toIso8601String())->toBe('2026-09-30T22:00:00+00:00');
});
test('persisted start is authoritative regardless of later configuration', function (mixed $value): void {
    $epoch = CarbonImmutable::parse('2026-10-01T00:00:00+02:00')->timestamp;
    $run = MaddraxikonStreakRun::query()->create(['wiki_key' => config('maddraxikon.wiki_key'), 'starts_at_epoch' => $epoch]);
    $before = $run->fresh()->getAttributes();
    config(['maddraxikon.streaks.starts_at' => $value, 'maddraxikon.streaks.enabled' => true]);
    $settings = app(MaddraxikonStreakSettings::class);
    expect($settings->start()->timestamp)->toBe($epoch)
        ->and($settings->enabled())->toBeTrue()
        ->and($settings->pinStart()->id)->toBe($run->id)
        ->and($run->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseCount('maddraxikon_streak_runs', 1);
})->with('MaddraxikonStreakSettingsTest.changedStarts');
test('stored start is scoped to the current wiki and read after activation', function (): void {
    $otherStart = CarbonImmutable::parse('2026-09-01T00:00:00Z')->timestamp;
    MaddraxikonStreakRun::query()->create(['wiki_key' => 'other-wiki', 'starts_at_epoch' => $otherStart]);
    config(['maddraxikon.streaks.starts_at' => '2026-10-01T00:00:00+02:00']);
    $settings = app(MaddraxikonStreakSettings::class);
    expect($settings->start()->toIso8601String())->toBe('2026-09-30T22:00:00+00:00');
    $epoch = CarbonImmutable::parse('2026-10-24T12:00:00Z')->timestamp;
    MaddraxikonStreakRun::query()->create(['wiki_key' => config('maddraxikon.wiki_key'), 'starts_at_epoch' => $epoch]);
    expect($settings->start()->timestamp)->toBe($epoch);
    config(['maddraxikon.wiki_key' => 'other-wiki']);
    expect($settings->start()->timestamp)->toBe($otherStart);
});
