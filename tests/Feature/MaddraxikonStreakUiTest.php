<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\EvaluateMaddraxikonStreaks;
use App\Livewire\MaddraxikonAdmin;
use App\Livewire\MaddraxikonStreakPanel;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonStreakDay;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use App\Models\User;
use App\Services\Maddraxikon\MaddraxikonStreakTracker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaddraxikonStreakUiTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-20T12:00:00+02:00'));
        config(['maddraxikon.streaks.enabled' => true, 'maddraxikon.streaks.starts_at' => '2026-10-01T00:00:00+02:00', 'maddraxikon.features.linking_enabled' => true, 'maddraxikon.features.sync_enabled' => true, 'maddraxikon.features.awards_enabled' => true]);
        $this->member = User::factory()->create(['current_team_id' => Team::membersTeam()->id]);
        Team::membersTeam()->users()->attach($this->member, ['role' => Role::Mitglied->value]);
        $this->actingAs($this->member);
    }

    public function test_unlinked_member_sees_call_to_action_in_all_three_entry_points(): void
    {
        foreach (['dashboard', 'profile.show', 'todos.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Maddraxikon-Serie')->assertSee('Maddraxikon verbinden');
        }
    }

    public function test_disabled_and_ineligible_states_hide_the_panel(): void
    {
        config(['maddraxikon.streaks.enabled' => false]);
        Livewire::test(MaddraxikonStreakPanel::class)->assertDontSee('Maddraxikon-Serie');
        config(['maddraxikon.streaks.enabled' => true]);
        Team::membersTeam()->users()->updateExistingPivot($this->member->id, ['role' => Role::Anwaerter->value]);
        Livewire::test(MaddraxikonStreakPanel::class)->assertDontSee('Maddraxikon-Serie');
    }

    public function test_linked_panel_shows_record_next_bonus_and_only_own_data(): void
    {
        MaddraxikonAccountLink::factory()->create(['user_id' => $this->member->id]);
        MaddraxikonSyncState::factory()->create(['initial_watermark_at' => '2026-09-01T00:00:00Z', 'last_succeeded_at' => now(), 'watermark_at' => now()]);
        MaddraxikonStreakDay::query()->create(['wiki_key' => config('maddraxikon.wiki_key'), 'user_id' => $this->member->id, 'activity_date' => '2026-10-19', 'status' => 'valid', 'streak_length' => 4, 'target_points' => 4, 'settled_points' => 4, 'starts_at_epoch' => 1]);
        $other = User::factory()->create();
        MaddraxikonStreakDay::query()->create(['wiki_key' => config('maddraxikon.wiki_key'), 'user_id' => $other->id, 'activity_date' => '2026-10-19', 'streak_length' => 999, 'settled_points' => 999, 'starts_at_epoch' => 1]);
        Livewire::test(MaddraxikonStreakPanel::class, ['detailed' => true])
            ->assertSee('4 Tage')->assertSee('+5 Bonus-Baxx')->assertSee('Persönlicher Rekord')
            ->assertSee('Die letzten 31 Tage')->assertDontSee('999');
    }

    public function test_stale_sync_does_not_claim_a_broken_series(): void
    {
        MaddraxikonAccountLink::factory()->create(['user_id' => $this->member->id]);
        Livewire::test(MaddraxikonStreakPanel::class)->assertSee('Synchronisation verzögert')->assertDontSee('Deine nächste Serie');
    }

    public function test_fresh_import_before_midnight_displays_delayed_instead_of_a_new_series(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20T00:05:00+02:00'));
        MaddraxikonAccountLink::factory()->create(['user_id' => $this->member->id]);
        MaddraxikonSyncState::factory()->create([
            'initial_watermark_at' => '2026-09-01T00:00:00Z',
            'last_succeeded_at' => now(), 'watermark_at' => '2026-10-19T23:55:00+02:00',
        ]);
        MaddraxikonStreakDay::query()->create([
            'wiki_key' => config('maddraxikon.wiki_key'), 'user_id' => $this->member->id,
            'activity_date' => '2026-10-18', 'status' => 'valid', 'streak_length' => 2,
            'target_points' => 2, 'settled_points' => 2, 'starts_at_epoch' => 1,
        ]);

        Livewire::test(MaddraxikonStreakPanel::class)
            ->assertViewHas('streak', fn ($streak) => $streak['current'] === 2 && $streak['next'] === null)
            ->assertSee('Synchronisation verzögert')->assertSee('zuletzt bestätigte Stand')
            ->assertDontSee('Deine nächste Serie')->assertDontSee('+1 Bonus-Baxx');
    }

    #[DataProvider('unsettledActivityDays')]
    public function test_valid_evidence_awaiting_reconciliation_displays_pending_activity(string $date, string $confirmed, string $message): void
    {
        $link = MaddraxikonAccountLink::factory()->create(['user_id' => $this->member->id, 'verified_at' => '2026-09-30T00:00:00Z']);
        MaddraxikonSyncState::factory()->create(['initial_watermark_at' => '2026-09-01T00:00:00Z', 'last_succeeded_at' => now(), 'watermark_at' => now()]);
        MaddraxikonStreakDay::query()->create([
            'wiki_key' => config('maddraxikon.wiki_key'), 'user_id' => $this->member->id,
            'activity_date' => $confirmed, 'status' => 'valid', 'streak_length' => 2,
            'target_points' => 2, 'settled_points' => 2, 'starts_at_epoch' => 1,
        ]);
        $source = MaddraxikonContribution::factory()->create([
            'user_id' => $this->member->id, 'account_link_id' => $link->id,
            'wiki_user_id' => $link->wiki_user_id, 'occurred_at' => $date.'T08:00:00+02:00',
        ]);
        app(MaddraxikonStreakTracker::class)->record($source, $link);
        $source->forceFill(['streak_status' => 'valid'])->save();

        Livewire::test(MaddraxikonStreakPanel::class)
            ->assertViewHas('streak', fn ($streak) => $streak['current'] === 2 && $streak['pending'])
            ->assertSee($message)->assertDontSee('Deine nächste Serie')->assertDontSee('+1 Bonus-Baxx');
    }

    public static function unsettledActivityDays(): array
    {
        return [
            'yesterday' => ['2026-10-19', '2026-10-18', 'Aktivitäten vorheriger Tage werden noch geprüft.'],
            'today' => ['2026-10-20', '2026-10-19', 'Aktivität erkannt – Bonus wird geprüft.'],
        ];
    }

    public function test_guest_cannot_read_component(): void
    {
        auth()->logout();
        Livewire::test(MaddraxikonStreakPanel::class)->assertForbidden();
    }

    public function test_admin_controls_require_role_and_audited_recovery_reason(): void
    {
        Bus::fake();
        Livewire::test(MaddraxikonAdmin::class)->assertForbidden();
        Team::membersTeam()->users()->updateExistingPivot($this->member->id, ['role' => Role::Admin->value]);
        $link = MaddraxikonAccountLink::factory()->create(['user_id' => $this->member->id]);
        MaddraxikonSyncState::factory()->create();
        $source = MaddraxikonContribution::factory()->create([
            'user_id' => $this->member->id, 'account_link_id' => $link->id,
            'status_reason' => 'recovery_bot_status_unverifiable',
        ]);
        Livewire::test(MaddraxikonAdmin::class)
            ->call('verifyStreakRecovery', $source->id)->assertHasErrors('streakReviewReason')
            ->set('streakReviewReason', 'Bot-Markierung anhand der Wiki-Historie geprüft.')
            ->call('verifyStreakRecovery', $source->id)->assertHasNoErrors()
            ->call('dispatchStreakReview');
        $this->assertTrue($source->fresh()->streak_bot_verified);
        Bus::assertDispatched(EvaluateMaddraxikonStreaks::class, fn ($job) => $job->recheck);
    }
}
