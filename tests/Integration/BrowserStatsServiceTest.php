<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\User;
use App\Services\BrowserStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMemberClientSnapshot;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesMemberClientSnapshot::class, RefreshDatabase::class);

covers(BrowserStatsService::class);

test('detect browser classifies known agents', function (): void {
    $service = app(BrowserStatsService::class);
    expect($service->detectBrowser('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0'))->toBe(['browser' => 'Microsoft Edge', 'family' => 'Chromium'])
        ->and($service->detectBrowser('Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/117.0'))->toBe(['browser' => 'Mozilla Firefox', 'family' => 'Firefox'])
        ->and($service->detectBrowser('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'))->toBe(['browser' => 'Safari', 'family' => 'WebKit'])
        ->and($service->detectBrowser('CustomBrowser/1.0 (Test)'))->toBe(['browser' => 'Andere', 'family' => 'Sonstige']);
});

test('detect device type classifies common agents', function (): void {
    $service = app(BrowserStatsService::class);
    expect($service->detectDeviceType('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'))->toBe('Mobilgerät')
        ->and($service->detectDeviceType('Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.144 Mobile Safari/537.36'))->toBe('Mobilgerät')
        ->and($service->detectDeviceType('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'))->toBe('Festgerät')
        ->and($service->detectDeviceType(null))->toBe('Festgerät');
});

test('detect browser version extracts supported versions', function (): void {
    $service = app(BrowserStatsService::class);
    $agents = ['120.0.0.0' => 'Mozilla/5.0 Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0', '106.0.0.0' => 'Mozilla/5.0 Chrome/120.0.0.0 Safari/537.36 OPR/106.0.0.0', '6.5.3206.53' => 'Mozilla/5.0 Chrome/120.0.0.0 Safari/537.36 Vivaldi/6.5.3206.53', '23.0' => 'Mozilla/5.0 Chrome/120.0.0.0 Mobile Safari/537.36 SamsungBrowser/23.0', '120.0.6099.144' => 'Mozilla/5.0 Chrome/120.0.6099.144 Safari/537.36', '117.0' => 'Mozilla/5.0 Gecko/20100101 Firefox/117.0', '17.0' => 'Mozilla/5.0 Version/17.0 Mobile/15E148 Safari/604.1', '11.0' => 'Mozilla/5.0 Trident/7.0; rv:11.0 like Gecko'];
    foreach ($agents as $expectedVersion => $userAgent) {
        expect($service->detectBrowserVersion($userAgent))->toBe($expectedVersion);
    }
    expect($service->detectBrowserVersion('CustomBrowser/1.0'))->toBe('Unbekannt')
        ->and($service->detectBrowserVersion(null))->toBe('Unbekannt');
});

test('browser usage counts all recent sessions per member', function (): void {
    $service = app(BrowserStatsService::class);
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $this->createSnapshot($userA->id, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0', now()->subDay());
    $this->createSnapshot($userA->id, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', now());
    $this->createSnapshot($userB->id, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', now());
    $usage = $service->browserUsage();
    $browserCounts = $usage['browserCounts']->pluck('value', 'label')->all();
    $familyCounts = $usage['familyCounts']->pluck('value', 'label')->all();
    $deviceTypeCounts = $usage['deviceTypeCounts']->pluck('value', 'label')->all();
    expect($browserCounts['Google Chrome'])->toBe(1)
        ->and($browserCounts['Safari'])->toBe(1)
        ->and($browserCounts['Mozilla Firefox'])->toBe(1)
        ->and($familyCounts['Chromium'])->toBe(1)
        ->and($familyCounts['WebKit'])->toBe(1)
        ->and($familyCounts['Firefox'])->toBe(1)
        ->and($deviceTypeCounts['Festgerät'])->toBe(2)
        ->and($deviceTypeCounts['Mobilgerät'])->toBe(1);
});

test('browser usage does not double count identical devices', function (): void {
    $service = app(BrowserStatsService::class);
    $user = User::factory()->create();
    $this->createSnapshot($user->id, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', now()->subMinutes(10));
    $this->createSnapshot($user->id, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', now()->subMinutes(5));
    $usage = $service->browserUsage();
    $browserCounts = $usage['browserCounts']->pluck('value', 'label')->all();
    $deviceTypeCounts = $usage['deviceTypeCounts']->pluck('value', 'label')->all();
    expect($browserCounts['Google Chrome'])->toBe(1)
        ->and($deviceTypeCounts['Festgerät'])->toBe(1);
});

test('browser usage handles user agents with pipe characters', function (): void {
    $service = app(BrowserStatsService::class);
    $user = User::factory()->create();
    $this->createSnapshot($user->id, 'Mozilla/5.0 (TestDevice|Special) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', now()->subMinutes(15));
    $this->createSnapshot($user->id, 'Mozilla/5.0 (Different Device) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', now()->subMinutes(5));
    $usage = $service->browserUsage();
    $browserCounts = $usage['browserCounts']->pluck('value', 'label')->all();
    expect($browserCounts['Google Chrome'])->toBe(1)
        ->and($browserCounts['Safari'])->toBe(1);
});

test('browser usage ignores sessions older than thirty days', function (): void {
    $service = app(BrowserStatsService::class);
    $user = User::factory()->create();
    $this->createSnapshot($user->id, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', now()->subDays(45));
    $this->createSnapshot($user->id, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', now()->subDays(2));
    $usage = $service->browserUsage();
    $browserCounts = $usage['browserCounts']->pluck('value', 'label')->all();
    expect($browserCounts)->not->toHaveKey('Google Chrome')
        ->and($browserCounts['Safari'])->toBe(1);
});
