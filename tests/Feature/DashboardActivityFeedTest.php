<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\DashboardActivityFeed;
use App\Models\Activity;
use App\Models\BookOffer;
use App\Models\FantreffenAnmeldung;
use App\Models\Review;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_page_has_fifteen_items_and_manual_fallback(): void
    {
        $member = $this->member();
        $this->activities($member, 16);

        Livewire::actingAs($member)
            ->test(DashboardActivityFeed::class)
            ->assertCount('activityIds', 15)
            ->assertSet('hasMore', true)
            ->assertSee('Weitere Aktivitäten laden')
            ->assertSeeHtml('data-dashboard-feed-sentinel');
    }

    public function test_feed_cursor_and_filter_indexes_are_present(): void
    {
        $indexNames = collect(Schema::getIndexes('activities'))->pluck('name');

        $this->assertContains('activities_feed_cursor_index', $indexNames);
        $this->assertContains('activities_feed_filter_cursor_index', $indexNames);
    }

    public function test_load_more_reaches_complete_history_without_duplicates(): void
    {
        $member = $this->member();
        $expectedIds = $this->activities($member, 32);

        $component = Livewire::actingAs($member)
            ->test(DashboardActivityFeed::class)
            ->assertCount('activityIds', 15)
            ->call('loadMore')
            ->assertCount('activityIds', 30)
            ->call('loadMore')
            ->assertCount('activityIds', 32)
            ->assertSet('hasMore', false)
            ->assertSee('Ende des Aktivitätsverlaufs');

        $loadedIds = $component->get('activityIds');

        $this->assertSame($expectedIds, $loadedIds);
        $this->assertSame($loadedIds, array_values(array_unique($loadedIds)));
    }

    public function test_cursor_is_stable_when_new_activity_arrives_between_pages(): void
    {
        $member = $this->member();
        $expectedOldIds = $this->activities($member, 20);

        $component = Livewire::actingAs($member)->test(DashboardActivityFeed::class);

        $newActivity = $this->activity($member, User::class, now()->addMinute());

        $component->call('loadMore')->assertCount('activityIds', 20);

        $this->assertSame($expectedOldIds, $component->get('activityIds'));
        $this->assertNotContains($newActivity->id, $component->get('activityIds'));
    }

    public function test_cursor_uses_id_as_tie_breaker_for_equal_timestamps(): void
    {
        $member = $this->member();
        $timestamp = now()->startOfSecond();
        $ids = [];

        for ($index = 0; $index < 31; $index++) {
            $ids[] = $this->activity($member, User::class, $timestamp)->id;
        }

        $component = Livewire::actingAs($member)
            ->test(DashboardActivityFeed::class)
            ->call('loadMore')
            ->call('loadMore');

        $this->assertSame(array_reverse($ids), $component->get('activityIds'));
    }

    public function test_filters_reset_results_and_invalid_filter_falls_back_to_all(): void
    {
        $member = $this->member();
        $review = $this->activity($member, Review::class);
        $swap = $this->activity($member, BookOffer::class);
        $baxx = $this->activity($member, Todo::class);
        $club = $this->activity($member, FantreffenAnmeldung::class);
        $reward = $this->activity($member, RewardPurchase::class);

        $component = Livewire::actingAs($member)
            ->test(DashboardActivityFeed::class)
            ->call('selectFilter', 'content')
            ->assertSet('filter', 'content')
            ->assertSet('activityIds', [$review->id])
            ->call('selectFilter', 'swap')
            ->assertSet('activityIds', [$swap->id])
            ->call('selectFilter', 'baxx');

        $this->assertEqualsCanonicalizing([$reward->id, $baxx->id], $component->get('activityIds'));

        $component
            ->call('selectFilter', 'club')
            ->assertSet('activityIds', [$club->id])
            ->call('selectFilter', 'unknown-filter')
            ->assertSet('filter', 'all')
            ->assertCount('activityIds', 5);
    }

    public function test_feed_renders_day_headings(): void
    {
        $member = $this->member();
        $this->activity($member, User::class, now());
        $this->activity($member, User::class, now()->subDay());

        Livewire::actingAs($member)
            ->test(DashboardActivityFeed::class)
            ->assertSee('Heute')
            ->assertSee('Gestern');
    }

    public function test_repeated_milestones_are_grouped_per_member_and_day(): void
    {
        $member = $this->member();

        foreach ([1, 10, 100] as $milestone) {
            $this->activity(
                $member,
                User::class,
                now(),
                "baxx_milestone_reached_{$milestone}",
            );
        }

        $this->activity($member, User::class, now()->subDay(), 'baxx_milestone_reached_250');

        Livewire::actingAs($member)
            ->test(DashboardActivityFeed::class)
            ->assertCount('activityIds', 4)
            ->assertSee('3 Baxx-Meilensteine erreicht: 1, 10 und 100 Baxx')
            ->assertSee('250 Baxx erreicht');
    }

    private function member(): User
    {
        $team = Team::membersTeam();
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        return $member;
    }

    /**
     * @return array<int, int>
     */
    private function activities(User $member, int $count): array
    {
        $ids = [];

        for ($index = 0; $index < $count; $index++) {
            $ids[] = $this->activity($member, User::class, now()->subMinutes($index))->id;
        }

        return $ids;
    }

    /**
     * @param  class-string  $subjectType
     */
    private function activity(
        User $member,
        string $subjectType,
        mixed $createdAt = null,
        string $action = 'member_approved',
    ): Activity {
        $activity = Activity::query()->create([
            'user_id' => $member->id,
            'subject_type' => $subjectType,
            'subject_id' => $member->id,
            'action' => $action,
        ]);

        if ($createdAt !== null) {
            $activity->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->saveQuietly();
        }

        return $activity->fresh();
    }
}
