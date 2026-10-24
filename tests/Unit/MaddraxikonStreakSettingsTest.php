<?php

namespace Tests\Unit;

use App\Services\Maddraxikon\MaddraxikonStreakSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaddraxikonStreakSettingsTest extends TestCase
{
    #[DataProvider('invalidStarts')]
    public function test_invalid_or_ambiguous_rollout_time_is_rejected(string $value): void
    {
        config(['maddraxikon.streaks.starts_at' => $value]);
        $this->expectException(\Throwable::class);
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
}
