<?php

namespace Tests\Unit;

use App\Models\MaddraxikonStreakRun;
use App\Services\Maddraxikon\MaddraxikonStreakSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaddraxikonStreakSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('invalidStarts')]
    public function test_invalid_or_ambiguous_rollout_time_is_rejected(string $value): void
    {
        config(['maddraxikon.streaks.starts_at' => $value]);
        $this->expectException(LogicException::class);
        app(MaddraxikonStreakSettings::class)->start();
    }

    public static function invalidStarts(): array
    {
        return [['tomorrow'], ['2026-10-01'], ['2026-10-01T12:00:00'], ['2026-02-30T12:00:00Z'], ['2026-10-01T24:00:00Z']];
    }

    public function test_rollout_offset_is_converted_to_utc(): void
    {
        config(['maddraxikon.streaks.starts_at' => '2026-10-01T00:00:00+02:00']);
        $this->assertSame('2026-09-30T22:00:00+00:00', app(MaddraxikonStreakSettings::class)->start()->toIso8601String());
    }

    #[DataProvider('changedStarts')]
    public function test_persisted_start_is_authoritative_regardless_of_later_configuration(mixed $value): void
    {
        $epoch = CarbonImmutable::parse('2026-10-01T00:00:00+02:00')->timestamp;
        $run = MaddraxikonStreakRun::query()->create(['wiki_key' => config('maddraxikon.wiki_key'), 'starts_at_epoch' => $epoch]);
        $before = $run->fresh()->getAttributes();
        config(['maddraxikon.streaks.starts_at' => $value, 'maddraxikon.streaks.enabled' => true]);

        $settings = app(MaddraxikonStreakSettings::class);
        $this->assertSame($epoch, $settings->start()->timestamp);
        $this->assertTrue($settings->enabled());
        $this->assertSame($run->id, $settings->pinStart()->id);
        $this->assertSame($before, $run->fresh()->getAttributes());
        $this->assertDatabaseCount('maddraxikon_streak_runs', 1);
    }

    public static function changedStarts(): array
    {
        return [
            'later' => ['2026-11-01T00:00:00+01:00'],
            'earlier' => ['2026-09-01T00:00:00+02:00'],
            'equivalent offset' => ['2026-09-30T22:00:00Z'],
            'invalid format' => ['tomorrow'],
            'invalid date' => ['2026-02-30T00:00:00Z'],
            'empty' => [''],
            'missing' => [null],
        ];
    }

    public function test_stored_start_is_scoped_to_the_current_wiki_and_read_after_activation(): void
    {
        $otherStart = CarbonImmutable::parse('2026-09-01T00:00:00Z')->timestamp;
        MaddraxikonStreakRun::query()->create(['wiki_key' => 'other-wiki', 'starts_at_epoch' => $otherStart]);
        config(['maddraxikon.streaks.starts_at' => '2026-10-01T00:00:00+02:00']);
        $settings = app(MaddraxikonStreakSettings::class);
        $this->assertSame('2026-09-30T22:00:00+00:00', $settings->start()->toIso8601String());

        $epoch = CarbonImmutable::parse('2026-10-24T12:00:00Z')->timestamp;
        MaddraxikonStreakRun::query()->create(['wiki_key' => config('maddraxikon.wiki_key'), 'starts_at_epoch' => $epoch]);
        $this->assertSame($epoch, $settings->start()->timestamp);
        config(['maddraxikon.wiki_key' => 'other-wiki']);
        $this->assertSame($otherStart, $settings->start()->timestamp);
    }
}
