<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\DashboardActivityFeed;
use App\Models\BookOffer;
use App\Models\FantreffenAnmeldung;
use App\Models\Review;
use App\Models\RewardPurchase;
use App\Models\Todo;
use App\Models\User;
use App\Services\Dashboard\DashboardActivityQuery;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(DashboardActivityFeedTestFixtures::class);

test('dashboard defers all feed queries until the component is loaded', function (): void {
    $member = $this->member();
    $this->partialMock(DashboardActivityQuery::class, function ($mock): void {
        $mock->shouldNotReceive('page');
        $mock->shouldNotReceive('findMany');
    });
    $this->actingAs($member)->get('/dashboard')->assertOk()->assertSeeText('Aktivitäten werden geladen')->assertSeeHtml('aria-busy="true"');
});

test('initial page has fifteen items and manual fallback', function (): void {
    $member = $this->member();
    $this->activities($member, 16);
    Livewire::actingAs($member)->test(DashboardActivityFeed::class)->assertCount('activityIds', 15)->assertSet('hasMore', true)->assertSee('Weitere Aktivitäten laden')->assertSeeHtml('data-dashboard-feed-sentinel');
});

test('feed cursor and filter indexes are present', function (): void {
    $indexNames = collect(Schema::getIndexes('activities'))->pluck('name');
    expect($indexNames)->toContain('activities_feed_cursor_index')
        ->toContain('activities_feed_filter_cursor_index');
});

test('load more reaches complete history without duplicates', function (): void {
    $member = $this->member();
    $expectedIds = $this->activities($member, 32);
    $component = Livewire::actingAs($member)->test(DashboardActivityFeed::class)->assertCount('activityIds', 15)->call('loadMore')->assertCount('activityIds', 30)->call('loadMore')->assertCount('activityIds', 32)->assertSet('hasMore', false)->assertSee('Ende des Aktivitätsverlaufs');
    $loadedIds = $component->get('activityIds');
    expect($loadedIds)->toBe($expectedIds)
        ->and(array_values(array_unique($loadedIds)))->toBe($loadedIds);
});

test('cursor is stable when new activity arrives between pages', function (): void {
    $member = $this->member();
    $expectedOldIds = $this->activities($member, 20);
    $component = Livewire::actingAs($member)->test(DashboardActivityFeed::class);
    $newActivity = $this->activity($member, User::class, now()->addMinute());
    $component->call('loadMore')->assertCount('activityIds', 20);
    expect($component->get('activityIds'))->toBe($expectedOldIds)
        ->and($component->get('activityIds'))->not->toContain($newActivity->id);
});

test('cursor uses id as tie breaker for equal timestamps', function (): void {
    $member = $this->member();
    $timestamp = now()->startOfSecond();
    $ids = [];
    for ($index = 0; $index < 31; $index++) {
        $ids[] = $this->activity($member, User::class, $timestamp)->id;
    }
    $component = Livewire::actingAs($member)->test(DashboardActivityFeed::class)->call('loadMore')->call('loadMore');
    expect($component->get('activityIds'))->toBe(array_reverse($ids));
});

test('filters reset results and invalid filter falls back to all', function (): void {
    $member = $this->member();
    $review = $this->activity($member, Review::class);
    $swap = $this->activity($member, BookOffer::class);
    $baxx = $this->activity($member, Todo::class);
    $club = $this->activity($member, FantreffenAnmeldung::class);
    $reward = $this->activity($member, RewardPurchase::class);
    $component = Livewire::actingAs($member)->test(DashboardActivityFeed::class)->call('selectFilter', 'content')->assertSet('filter', 'content')->assertSet('activityIds', [$review->id])->call('selectFilter', 'swap')->assertSet('activityIds', [$swap->id])->call('selectFilter', 'baxx');
    expect($component->get('activityIds'))->toEqualCanonicalizing([$reward->id, $baxx->id]);
    $component->call('selectFilter', 'club')->assertSet('activityIds', [$club->id])->call('selectFilter', 'unknown-filter')->assertSet('filter', 'all')->assertCount('activityIds', 5);
});

test('feed renders day headings', function (): void {
    $member = $this->member();
    $this->activity($member, User::class, now());
    $this->activity($member, User::class, now()->subDay());
    Livewire::actingAs($member)->test(DashboardActivityFeed::class)->assertSee('Heute')->assertSee('Gestern');
});

test('repeated milestones are grouped per member and day', function (): void {
    $member = $this->member();
    foreach ([1, 10, 100] as $milestone) {
        $this->activity($member, User::class, now(), "baxx_milestone_reached_{$milestone}");
    }
    $this->activity($member, User::class, now()->subDay(), 'baxx_milestone_reached_250');
    Livewire::actingAs($member)->test(DashboardActivityFeed::class)->assertCount('activityIds', 4)->assertSee('3 Baxx-Meilensteine erreicht: 1, 10 und 100 Baxx')->assertSee('250 Baxx erreicht');
});
