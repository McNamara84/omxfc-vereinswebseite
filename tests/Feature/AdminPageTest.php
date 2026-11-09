<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PageVisit;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Large;
use Tests\Support\PhpunitMetadata;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(AdminPageTestFixtures::class);

test('admin route denied for unauthorized roles', function (): void {
    $member = $this->memberUser();
    $this->actingAs($member)->get('/admin/statistiken')->assertStatus(403);
});

test('admin route allows vorstand and kassenwart', function (): void {
    $vorstand = $this->vorstandUser();
    $kassenwart = $this->kassenwartUser();
    $this->actingAs($vorstand)->get('/admin/statistiken')->assertOk();
    $this->actingAs($kassenwart)->get('/admin/statistiken')->assertOk();
});

test('page visit logged and admin can view', function (): void {
    $admin = $this->adminUser();
    $this->actingAs($admin)->withHeader('User-Agent', 'TestAgent/1.0')->get('/dashboard')->assertOk();
    $this->assertDatabaseHas('page_visits', ['user_id' => $admin->id, 'path' => '/dashboard']);
    $this->assertDatabaseHas('member_client_snapshots', ['user_id' => $admin->id, 'user_agent' => 'TestAgent/1.0']);
    $this->actingAs($admin)->get('/admin/statistiken')->assertOk();
});

test('statistik charts render with accessible wrapper', function (): void {
    $admin = $this->adminUser();
    $response = $this->actingAs($admin)->get('/admin/statistiken');
    $response->assertOk();
    $response->assertSee('data-chart-wrapper', false);
    $response->assertSee('role="img"', false);
    $response->assertSee('aria-labelledby="homepage-visits-heading"', false);
});

test('homepage visits are separated and routes are grouped', function (): void {
    $admin = $this->adminUser();
    $member = $this->memberUser();
    PageVisit::create(['user_id' => $admin->id, 'path' => '/']);
    PageVisit::create(['user_id' => $admin->id, 'path' => '/?ref=promo']);
    PageVisit::create(['user_id' => $member->id, 'path' => '/']);
    PageVisit::create(['user_id' => $admin->id, 'path' => '/rezensionen']);
    PageVisit::create(['user_id' => $member->id, 'path' => '/rezensionen/660']);
    PageVisit::create(['user_id' => $member->id, 'path' => '/rezensionen/660/edit']);
    $response = $this->actingAs($admin)->get('/admin/statistiken');
    $response->assertOk();
    $response->assertViewHas('homepageVisits', 3);
    $response->assertSeeText('Seitenaufrufe nach Route');
    $response->assertSee('aria-labelledby="route-visits-heading"', false);
    $response->assertViewHas('visitData', function ($data) {
        $collection = collect($data);
        $rezensionen = $collection->firstWhere('path', '/rezensionen');
        expect($rezensionen)->not->toBeNull()
            ->and($rezensionen['total'])->toBe(3)
            ->and($collection->firstWhere('path', '/rezensionen/660'))->toBeNull()
            ->and($collection->contains(fn ($row) => $row['path'] === '/'))->toBeFalse();

        return true;
    });
    $response->assertViewHas('userVisitData', function ($data) use ($member) {
        $collection = collect($data);
        $memberEntry = $collection->firstWhere(fn ($row) => $row['path'] === '/rezensionen' && $row['user_id'] === $member->id);
        expect($memberEntry)->not->toBeNull()
            ->and($memberEntry['total'])->toBe(2)
            ->and($collection->firstWhere('path', '/rezensionen/660'))->toBeNull();

        return true;
    });
    $response->assertViewHas('activityData', function ($data) {
        expect($data)->toBeArray()
            ->toHaveKey('all')
            ->and($data['all'])->toHaveCount(24);

        return true;
    });
    $response->assertViewHas('activityTimeline', function ($data) {
        $collection = collect($data);
        expect($collection)->toHaveCount(7 * 24);
        $firstEntry = $collection->first();
        expect($firstEntry)->toBeArray()
            ->toHaveKey('weekday')
            ->toHaveKey('hour')
            ->toHaveKey('total');

        return true;
    });
    // Chart-Logik ist in admin/charts.js extrahiert – Config-Element mit Daten prüfen
    $response->assertSee('id="admin-charts-config"', false);
    $response->assertSee('id="activeUsersWeekdayChart"', false);
    $response->assertSee('aria-describedby="active-users-weekday-description"', false);
});

test('browser usage statistics visible for admins', function (): void {
    $admin = $this->adminUser();
    $member = $this->memberUser();
    $this->createSnapshot($admin->id, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36', now());
    $this->createSnapshot($member->id, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', now());
    $this->createSnapshot($member->id, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', now()->addSecond());
    $response = $this->actingAs($admin)->get('/admin/statistiken');
    $response->assertOk();
    $response->assertSee('Browsernutzung unserer Mitglieder');
    $response->assertSee('Beliebteste Browser');
    $response->assertSee('Browser-Familien');
    $response->assertSee('Endgeräte unserer Mitglieder');
    $response->assertSeeText('Google Chrome');
    $response->assertSeeText('Safari');
    $response->assertSeeText('Chromium');
    $response->assertSeeText('WebKit');
    $response->assertSeeText('Festgerät');
    $response->assertSeeText('Mobilgerät');
    $response->assertDontSee('Noch keine Login-Daten vorhanden.');
});

test('daily active users card displays unique logins', function (): void {
    Carbon::setTestNow('2024-05-10 12:00:00');
    $admin = $this->adminUser();
    $member = $this->memberUser();
    $anotherMember = $this->memberUser();
    PageVisit::query()->insert(['user_id' => $admin->id, 'path' => '/dashboard', 'created_at' => Carbon::now()->subHours(2), 'updated_at' => Carbon::now()->subHours(2)]);
    PageVisit::query()->insert(['user_id' => $member->id, 'path' => '/dashboard', 'created_at' => Carbon::now()->subHours(1), 'updated_at' => Carbon::now()->subHours(1)]);
    PageVisit::query()->insert(['user_id' => $member->id, 'path' => '/dashboard', 'created_at' => Carbon::now()->subHours(1)->addMinutes(10), 'updated_at' => Carbon::now()->subHours(1)->addMinutes(10)]);
    PageVisit::query()->insert(['user_id' => $anotherMember->id, 'path' => '/dashboard', 'created_at' => Carbon::now()->subDay(), 'updated_at' => Carbon::now()->subDay()]);
    $response = $this->actingAs($admin)->get('/admin/statistiken');
    $response->assertOk();
    $response->assertViewHas('dailyActiveUsers', function ($card) {
        expect($card)->toBeArray()
            ->and($card['today'])->toBe(2)
            ->and($card['yesterday'])->toBe(1)
            ->and($card['trend'])->toBe(2 - 1)
            ->and($card['series'])->toBeArray()
            ->toHaveCount(30);

        return true;
    });
    $response->assertSeeText('Daily Active Users');
    $response->assertSeeText('Aktive Mitglieder heute');
    $response->assertSeeText('7-Tage-Schnitt');
    Carbon::setTestNow();
});

PhpunitMetadata::onFile(__FILE__, Large::class);
