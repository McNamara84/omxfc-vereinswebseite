<?php

namespace Tests\Unit;

use App\Models\MemberClientSnapshot;
use App\Models\User;
use App\Services\BrowserStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowserStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_detect_browser_classifies_known_agents(): void
    {
        $service = app(BrowserStatsService::class);

        $this->assertSame(
            ['browser' => 'Microsoft Edge', 'family' => 'Chromium'],
            $service->detectBrowser('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0')
        );

        $this->assertSame(
            ['browser' => 'Mozilla Firefox', 'family' => 'Firefox'],
            $service->detectBrowser('Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/117.0')
        );

        $this->assertSame(
            ['browser' => 'Safari', 'family' => 'WebKit'],
            $service->detectBrowser('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1')
        );

        $this->assertSame(
            ['browser' => 'Andere', 'family' => 'Sonstige'],
            $service->detectBrowser('CustomBrowser/1.0 (Test)')
        );
    }

    public function test_detect_device_type_classifies_common_agents(): void
    {
        $service = app(BrowserStatsService::class);

        $this->assertSame('Mobilgerät', $service->detectDeviceType('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'));
        $this->assertSame('Mobilgerät', $service->detectDeviceType('Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.144 Mobile Safari/537.36'));
        $this->assertSame('Festgerät', $service->detectDeviceType('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'));
        $this->assertSame('Festgerät', $service->detectDeviceType(null));
    }

    public function test_browser_usage_counts_all_recent_sessions_per_member(): void
    {
        $service = app(BrowserStatsService::class);

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->createSnapshot($userA->id, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0', now()->subDay());
        $this->createSnapshot($userA->id, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', now());
        $this->createSnapshot($userB->id, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, wie Gecko) Version/17.0 Mobile/15E148 Safari/604.1', now());

        $usage = $service->browserUsage();

        $browserCounts = $usage['browserCounts']->pluck('value', 'label')->all();
        $familyCounts = $usage['familyCounts']->pluck('value', 'label')->all();
        $deviceTypeCounts = $usage['deviceTypeCounts']->pluck('value', 'label')->all();

        $this->assertSame(1, $browserCounts['Google Chrome']);
        $this->assertSame(1, $browserCounts['Safari']);
        $this->assertSame(1, $browserCounts['Mozilla Firefox']);

        $this->assertSame(1, $familyCounts['Chromium']);
        $this->assertSame(1, $familyCounts['WebKit']);
        $this->assertSame(1, $familyCounts['Firefox']);

        $this->assertSame(2, $deviceTypeCounts['Festgerät']);
        $this->assertSame(1, $deviceTypeCounts['Mobilgerät']);
    }

    public function test_browser_usage_does_not_double_count_identical_devices(): void
    {
        $service = app(BrowserStatsService::class);

        $user = User::factory()->create();

        $this->createSnapshot($user->id, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', now()->subMinutes(10));
        $this->createSnapshot($user->id, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', now()->subMinutes(5));

        $usage = $service->browserUsage();

        $browserCounts = $usage['browserCounts']->pluck('value', 'label')->all();
        $deviceTypeCounts = $usage['deviceTypeCounts']->pluck('value', 'label')->all();

        $this->assertSame(1, $browserCounts['Google Chrome']);
        $this->assertSame(1, $deviceTypeCounts['Festgerät']);
    }

    public function test_browser_usage_handles_user_agents_with_pipe_characters(): void
    {
        $service = app(BrowserStatsService::class);

        $user = User::factory()->create();

        $this->createSnapshot($user->id, 'Mozilla/5.0 (TestDevice|Special) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', now()->subMinutes(15));
        $this->createSnapshot($user->id, 'Mozilla/5.0 (Different Device) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', now()->subMinutes(5));

        $usage = $service->browserUsage();

        $browserCounts = $usage['browserCounts']->pluck('value', 'label')->all();

        $this->assertSame(1, $browserCounts['Google Chrome']);
        $this->assertSame(1, $browserCounts['Safari']);
    }

    public function test_browser_usage_ignores_sessions_older_than_thirty_days(): void
    {
        $service = app(BrowserStatsService::class);

        $user = User::factory()->create();

        $this->createSnapshot($user->id, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', now()->subDays(45));
        $this->createSnapshot($user->id, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, wie Gecko) Version/17.0 Mobile/15E148 Safari/604.1', now()->subDays(2));

        $usage = $service->browserUsage();

        $browserCounts = $usage['browserCounts']->pluck('value', 'label')->all();

        $this->assertArrayNotHasKey('Google Chrome', $browserCounts);
        $this->assertSame(1, $browserCounts['Safari']);
    }

    private function createSnapshot(int $userId, ?string $userAgent, $lastSeenAt): void
    {
        MemberClientSnapshot::updateOrCreate(
            [
                'user_id' => $userId,
                'user_agent_hash' => MemberClientSnapshot::hashUserAgent($userAgent),
            ],
            [
                'user_agent' => $userAgent,
                'last_seen_at' => $lastSeenAt,
            ]
        );
    }
}
