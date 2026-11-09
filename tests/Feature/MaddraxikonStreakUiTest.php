<?php

declare(strict_types=1);

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
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonStreakUiTestFixtures::class);
dataset('MaddraxikonStreakUiTest.unsettledActivityDays', static fn (): array => ['yesterday' => ['2026-10-19', '2026-10-18', 'Aktivitäten vorheriger Tage werden noch geprüft.'], 'today' => ['2026-10-20', '2026-10-19', 'Aktivität erkannt – Bonus wird geprüft.']]);
beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-20T12:00:00+02:00'));
    config(['maddraxikon.streaks.enabled' => true, 'maddraxikon.streaks.starts_at' => '2026-10-01T00:00:00+02:00', 'maddraxikon.features.linking_enabled' => true, 'maddraxikon.features.sync_enabled' => true, 'maddraxikon.features.awards_enabled' => true]);
    $this->member = User::factory()->create(['current_team_id' => Team::membersTeam()->id]);
    Team::membersTeam()->users()->attach($this->member, ['role' => Role::Mitglied->value]);
    $this->actingAs($this->member);
});
test('unlinked member sees call to action in all three entry points', function (): void {
    foreach (['dashboard', 'profile.show', 'todos.index'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('Maddraxikon-Serie')->assertSee('Maddraxikon verbinden');
    }
});
test('disabled and ineligible states hide the panel', function (): void {
    config(['maddraxikon.streaks.enabled' => false]);
    Livewire::test(MaddraxikonStreakPanel::class)->assertDontSee('Maddraxikon-Serie');
    config(['maddraxikon.streaks.enabled' => true]);
    Team::membersTeam()->users()->updateExistingPivot($this->member->id, ['role' => Role::Anwaerter->value]);
    Livewire::test(MaddraxikonStreakPanel::class)->assertDontSee('Maddraxikon-Serie');
});
test('linked panel shows record next bonus and only own data', function (): void {
    MaddraxikonAccountLink::factory()->create(['user_id' => $this->member->id]);
    MaddraxikonSyncState::factory()->create(['initial_watermark_at' => '2026-09-01T00:00:00Z', 'last_succeeded_at' => now(), 'watermark_at' => now()]);
    MaddraxikonStreakDay::query()->create(['wiki_key' => config('maddraxikon.wiki_key'), 'user_id' => $this->member->id, 'activity_date' => '2026-10-19', 'status' => 'valid', 'streak_length' => 4, 'target_points' => 4, 'settled_points' => 4, 'starts_at_epoch' => 1]);
    $other = User::factory()->create();
    MaddraxikonStreakDay::query()->create(['wiki_key' => config('maddraxikon.wiki_key'), 'user_id' => $other->id, 'activity_date' => '2026-10-19', 'streak_length' => 999, 'settled_points' => 999, 'starts_at_epoch' => 1]);
    Livewire::test(MaddraxikonStreakPanel::class, ['detailed' => true])->assertSee('4 Tage')->assertSee('+5 Bonus-Baxx')->assertSee('Persönlicher Rekord')->assertSee('Die letzten 31 Tage')->assertDontSee('999');
});
test('stale sync does not claim a broken series', function (): void {
    MaddraxikonAccountLink::factory()->create(['user_id' => $this->member->id]);
    Livewire::test(MaddraxikonStreakPanel::class)->assertSee('Synchronisation verzögert')->assertDontSee('Deine nächste Serie');
});
test('fresh import before midnight displays delayed instead of a new series', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-20T00:05:00+02:00'));
    MaddraxikonAccountLink::factory()->create(['user_id' => $this->member->id]);
    MaddraxikonSyncState::factory()->create(['initial_watermark_at' => '2026-09-01T00:00:00Z', 'last_succeeded_at' => now(), 'watermark_at' => '2026-10-19T23:55:00+02:00']);
    MaddraxikonStreakDay::query()->create(['wiki_key' => config('maddraxikon.wiki_key'), 'user_id' => $this->member->id, 'activity_date' => '2026-10-18', 'status' => 'valid', 'streak_length' => 2, 'target_points' => 2, 'settled_points' => 2, 'starts_at_epoch' => 1]);
    Livewire::test(MaddraxikonStreakPanel::class)->assertViewHas('streak', fn ($streak) => $streak['current'] === 2 && $streak['next'] === null)->assertSee('Synchronisation verzögert')->assertSee('zuletzt bestätigte Stand')->assertDontSee('Deine nächste Serie')->assertDontSee('+1 Bonus-Baxx');
});
test('valid evidence awaiting reconciliation displays pending activity', function (string $date, string $confirmed, string $message): void {
    $link = MaddraxikonAccountLink::factory()->create(['user_id' => $this->member->id, 'verified_at' => '2026-09-30T00:00:00Z']);
    MaddraxikonSyncState::factory()->create(['initial_watermark_at' => '2026-09-01T00:00:00Z', 'last_succeeded_at' => now(), 'watermark_at' => now()]);
    MaddraxikonStreakDay::query()->create(['wiki_key' => config('maddraxikon.wiki_key'), 'user_id' => $this->member->id, 'activity_date' => $confirmed, 'status' => 'valid', 'streak_length' => 2, 'target_points' => 2, 'settled_points' => 2, 'starts_at_epoch' => 1]);
    $source = MaddraxikonContribution::factory()->create(['user_id' => $this->member->id, 'account_link_id' => $link->id, 'wiki_user_id' => $link->wiki_user_id, 'occurred_at' => $date.'T08:00:00+02:00']);
    app(MaddraxikonStreakTracker::class)->record($source, $link);
    $source->forceFill(['streak_status' => 'valid'])->save();
    Livewire::test(MaddraxikonStreakPanel::class)->assertViewHas('streak', fn ($streak) => $streak['current'] === 2 && $streak['pending'])->assertSee($message)->assertDontSee('Deine nächste Serie')->assertDontSee('+1 Bonus-Baxx');
})->with('MaddraxikonStreakUiTest.unsettledActivityDays');
test('guest cannot read component', function (): void {
    auth()->logout();
    Livewire::test(MaddraxikonStreakPanel::class)->assertForbidden();
});
test('admin controls require role and audited recovery reason', function (): void {
    Bus::fake();
    Livewire::test(MaddraxikonAdmin::class)->assertForbidden();
    Team::membersTeam()->users()->updateExistingPivot($this->member->id, ['role' => Role::Admin->value]);
    $link = MaddraxikonAccountLink::factory()->create(['user_id' => $this->member->id]);
    MaddraxikonSyncState::factory()->create();
    $source = MaddraxikonContribution::factory()->create(['user_id' => $this->member->id, 'account_link_id' => $link->id, 'status_reason' => 'recovery_bot_status_unverifiable']);
    Livewire::test(MaddraxikonAdmin::class)->call('verifyStreakRecovery', $source->id)->assertHasErrors('streakReviewReason')->set('streakReviewReason', 'Bot-Markierung anhand der Wiki-Historie geprüft.')->call('verifyStreakRecovery', $source->id)->assertHasNoErrors()->call('dispatchStreakReview');
    expect($source->fresh()->streak_bot_verified)->toBeTrue();
    Bus::assertDispatched(EvaluateMaddraxikonStreaks::class, fn ($job) => $job->recheck);
});
