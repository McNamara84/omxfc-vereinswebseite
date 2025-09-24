<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\BrowserStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        DB::table('sessions')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $userA->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
            'payload' => 'test',
            'last_activity' => now()->subDay()->timestamp,
        ]);

        DB::table('sessions')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $userA->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        DB::table('sessions')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $userB->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

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

        DB::table('sessions')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'payload' => 'test',
            'last_activity' => now()->subMinutes(10)->timestamp,
        ]);

        DB::table('sessions')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'payload' => 'test',
            'last_activity' => now()->subMinutes(5)->timestamp,
        ]);

        $usage = $service->browserUsage();

        $browserCounts = $usage['browserCounts']->pluck('value', 'label')->all();
        $deviceTypeCounts = $usage['deviceTypeCounts']->pluck('value', 'label')->all();

        $this->assertSame(1, $browserCounts['Google Chrome']);
        $this->assertSame(1, $deviceTypeCounts['Festgerät']);
    }

    public function test_browser_usage_ignores_sessions_older_than_thirty_days(): void
    {
        $service = app(BrowserStatsService::class);

        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'payload' => 'test',
            'last_activity' => now()->subDays(45)->timestamp,
        ]);

        DB::table('sessions')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'payload' => 'test',
            'last_activity' => now()->subDays(2)->timestamp,
        ]);

        $usage = $service->browserUsage();

        $browserCounts = $usage['browserCounts']->pluck('value', 'label')->all();

        $this->assertArrayNotHasKey('Google Chrome', $browserCounts);
        $this->assertSame(1, $browserCounts['Safari']);
    }
}
