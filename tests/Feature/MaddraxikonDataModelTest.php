<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonContributionType;
use App\Enums\MaddraxikonRewardEventStatus;
use App\Models\BaxxEarningRule;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonIdentityTombstone;
use App\Models\MaddraxikonRewardEvent;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\BaxxEarningRuleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('legacy profile username column is removed', function (): void {
    expect(Schema::hasColumn('users', 'maddraxikon_username'))->toBeFalse();
});

test('legacy profile migration preserves only releases with active links', function (): void {
    $migration = require database_path('migrations/2026_08_07_120000_drop_maddraxikon_username_from_users_table.php');
    $migration->down();
    try {
        $originalReleaseTime = CarbonImmutable::parse('2026-08-01T10:00:00Z');
        $migrationTime = CarbonImmutable::parse('2026-08-07T12:00:00Z');
        $activeUser = User::factory()->create(['contact_release_maddraxikon' => true, 'contact_released_at' => $originalReleaseTime]);
        MaddraxikonAccountLink::factory()->for($activeUser)->create();
        $disconnectedUser = User::factory()->create(['contact_release_maddraxikon' => true, 'contact_released_at' => $originalReleaseTime]);
        MaddraxikonAccountLink::factory()->disconnected()->for($disconnectedUser)->create();
        $unlinkedUser = User::factory()->create(['contact_release_maddraxikon' => true, 'contact_released_at' => $originalReleaseTime]);
        DB::table('users')->where('id', $activeUser->id)->update(['maddraxikon_username' => 'Aktiver Altname']);
        DB::table('users')->where('id', $disconnectedUser->id)->update(['maddraxikon_username' => 'Getrennter Altname']);
        DB::table('users')->where('id', $unlinkedUser->id)->update(['maddraxikon_username' => 'Unverknüpfter Altname']);
        CarbonImmutable::setTestNow($migrationTime);
        $migration->up();
        CarbonImmutable::setTestNow();
        expect(Schema::hasColumn('users', 'maddraxikon_username'))->toBeFalse()
            ->and(DB::table('users')->where('id', $activeUser->id)->value('contact_release_maddraxikon'))->toBeTruthy()
            ->and(DB::table('users')->where('id', $activeUser->id)->value('contact_released_at'))->toBe($originalReleaseTime->format('Y-m-d H:i:s'));
        foreach ([$disconnectedUser, $unlinkedUser] as $user) {
            expect(DB::table('users')->where('id', $user->id)->value('contact_release_maddraxikon'))->toBeFalsy()
                ->and(DB::table('users')->where('id', $user->id)->value('contact_released_at'))->toBe($migrationTime->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'));
        }
        $migration->down();
        expect(Schema::hasColumn('users', 'maddraxikon_username'))->toBeTrue()
            ->and(DB::table('users')->where('id', $activeUser->id)->value('maddraxikon_username'))->toBeNull()
            ->and(DB::table('users')->where('id', $disconnectedUser->id)->value('maddraxikon_username'))->toBeNull()
            ->and(DB::table('users')->where('id', $unlinkedUser->id)->value('maddraxikon_username'))->toBeNull();
    } finally {
        CarbonImmutable::setTestNow();
        if (Schema::hasColumn('users', 'maddraxikon_username')) {
            $migration->up();
        }
    }
});

test('all maddraxikon tables and ledger columns exist', function (): void {
    expect(Schema::hasColumns('maddraxikon_account_links', ['user_id', 'wiki_key', 'oauth_subject', 'wiki_user_id', 'wiki_username', 'status', 'first_verified_at', 'first_verified_at_epoch', 'verified_at', 'verified_at_epoch', 'disconnected_at', 'disconnected_at_epoch', 'consent_version', 'consented_at', 'consented_at_epoch']))->toBeTrue()
        ->and(Schema::hasColumns('maddraxikon_identity_tombstones', ['wiki_key', 'hash_key_version', 'hash_key_fingerprint', 'oauth_subject_hash', 'wiki_user_id_hash', 'retired_at']))->toBeTrue()
        ->and(Schema::hasColumns('maddraxikon_contributions', ['rc_id', 'revision_id', 'page_id', 'namespace_id', 'account_link_id', 'user_id', 'tags', 'occurred_at', 'occurred_at_epoch', 'eligible_after', 'eligible_after_epoch', 'status']))->toBeTrue()
        ->and(Schema::hasColumns('maddraxikon_reward_events', ['source_key', 'source_contribution_id', 'source_revision_id', 'baxx_earning_rule_id', 'candidate_points', 'awarded_points', 'capped_points', 'user_point_id', 'reversal_user_point_id', 'activity_pending', 'reversed_by', 'reversal_reason']))->toBeTrue()
        ->and(Schema::hasColumns('maddraxikon_sync_states', ['wiki_key', 'watermark_at', 'watermark_at_epoch', 'initial_watermark_at', 'initial_watermark_at_epoch', 'last_started_at', 'last_started_at_epoch', 'run_sequence', 'last_succeeded_at', 'last_succeeded_at_epoch', 'last_error_at', 'last_error_at_epoch', 'last_error', 'consecutive_failures', 'last_imported_count', 'last_seen_rc_id', 'recovery_required_at', 'recovery_required_at_epoch', 'recovery_from_at', 'recovery_from_at_epoch', 'recovery_until_at', 'recovery_until_at_epoch', 'last_recovery_succeeded_at', 'last_recovery_succeeded_at_epoch', 'last_recovered_from_at', 'last_recovered_from_at_epoch', 'last_recovered_until_at', 'last_recovered_until_at_epoch', 'last_recovered_count']))->toBeTrue()
        ->and(Schema::hasIndex('maddraxikon_reward_events', 'maddraxikon_rewards_user_action_sequence_index'))->toBeTrue()
        ->and(Schema::hasIndex('maddraxikon_reward_events', 'maddraxikon_rewards_pending_activity_index'))->toBeTrue();
    // Statuses deliberately remain portable strings instead of DB enums.
    expect(Schema::getColumnType('maddraxikon_account_links', 'status'))->toBe('varchar');
    expect(Schema::getColumnType('maddraxikon_contributions', 'status'))->toBe('varchar')
        ->and(Schema::getColumnType('maddraxikon_reward_events', 'status'))->toBe('varchar');
});

test('account link casts scope and user relations', function (): void {
    $user = User::factory()->create();
    $link = MaddraxikonAccountLink::factory()->create(['user_id' => $user->id, 'wiki_user_id' => 7301])->fresh();
    $contribution = MaddraxikonContribution::factory()->create(['account_link_id' => $link->id, 'user_id' => $user->id]);
    $rewardEvent = MaddraxikonRewardEvent::factory()->create(['source_contribution_id' => $contribution->id, 'account_link_id' => $link->id, 'user_id' => $user->id]);
    expect($link->status)->toBe(MaddraxikonAccountLinkStatus::Active)
        ->and($link->first_verified_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($link->verified_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($link->consented_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($link->isActive())->toBeTrue()
        ->and(MaddraxikonAccountLink::active()->pluck('id')->all())->toBe([$link->id])
        ->and($link->user->is($user))->toBeTrue()
        ->and($user->maddraxikonAccountLink->is($link))->toBeTrue()
        ->and($user->maddraxikonContributions->contains($contribution))->toBeTrue()
        ->and($user->maddraxikonRewardEvents->contains($rewardEvent))->toBeTrue()
        ->and($link->contributions->contains($contribution))->toBeTrue()
        ->and($link->rewardEvents->contains($rewardEvent))->toBeTrue();
});

test('disconnected link is not active', function (): void {
    $link = MaddraxikonAccountLink::factory()->disconnected()->create()->fresh();
    expect($link->status)->toBe(MaddraxikonAccountLinkStatus::Disconnected)
        ->and($link->disconnected_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($link->isActive())->toBeFalse()
        ->and(MaddraxikonAccountLink::active()->whereKey($link)->exists())->toBeFalse();
});

test('account link instants remain distinct during fall dst overlap', function (): void {
    $firstInstant = CarbonImmutable::parse('2026-10-25T00:30:00Z');
    $secondInstant = CarbonImmutable::parse('2026-10-25T01:30:00Z');
    $first = MaddraxikonAccountLink::factory()->create(['first_verified_at' => $firstInstant, 'verified_at' => $firstInstant, 'consented_at' => $firstInstant])->fresh();
    $second = MaddraxikonAccountLink::factory()->create(['first_verified_at' => $secondInstant, 'verified_at' => $secondInstant, 'consented_at' => $secondInstant])->fresh();
    expect($second->verified_at->setTimezone('Europe/Berlin')->format('Y-m-d H:i'))->toBe($first->verified_at->setTimezone('Europe/Berlin')->format('Y-m-d H:i'))
        ->and($first->verified_at_epoch)->toBe($firstInstant->timestamp)
        ->and($second->verified_at_epoch)->toBe($secondInstant->timestamp)
        ->and($first->verified_at->timestamp)->toBe($firstInstant->timestamp)
        ->and($second->verified_at->timestamp)->toBe($secondInstant->timestamp)
        ->and($second->verified_at_epoch)->not->toBe($first->verified_at_epoch);
    $first->update(['disconnected_at' => $firstInstant]);
    $second->update(['disconnected_at' => $secondInstant]);
    expect($first->fresh()->disconnected_at->timestamp)->toBe($firstInstant->timestamp)
        ->and($second->fresh()->disconnected_at->timestamp)->toBe($secondInstant->timestamp);
});

test('one club user cannot have two historical links', function (): void {
    $link = MaddraxikonAccountLink::factory()->disconnected()->create();
    expect(fn () => MaddraxikonAccountLink::factory()->create(['user_id' => $link->user_id]))->toThrow(QueryException::class);
});

test('oauth subject cannot be reclaimed after disconnect', function (): void {
    $link = MaddraxikonAccountLink::factory()->disconnected()->create(['oauth_subject' => 'opaque-subject-that-is-not-a-username']);
    expect(fn () => MaddraxikonAccountLink::factory()->create(['oauth_subject' => $link->oauth_subject]))->toThrow(QueryException::class);
});

test('local wiki user id cannot be reclaimed after disconnect', function (): void {
    $link = MaddraxikonAccountLink::factory()->disconnected()->create(['wiki_user_id' => 991100]);
    expect(fn () => MaddraxikonAccountLink::factory()->create(['wiki_user_id' => $link->wiki_user_id]))->toThrow(QueryException::class);
});

test('contribution casts due scope and relations', function (): void {
    $link = MaddraxikonAccountLink::factory()->create();
    $due = MaddraxikonContribution::factory()->create(['account_link_id' => $link->id, 'user_id' => $link->user_id, 'type' => MaddraxikonContributionType::New, 'status' => MaddraxikonContributionStatus::Pending, 'minor' => 1, 'tags' => ['mw-new-redirect', 'visualeditor'], 'eligible_after' => now()->subSecond(), 'checked_at' => now()])->fresh();
    $future = MaddraxikonContribution::factory()->create(['eligible_after' => now()->addMinute()]);
    MaddraxikonContribution::factory()->create(['eligible_after' => now()->subMinute(), 'status' => MaddraxikonContributionStatus::Rejected]);
    expect($due->type)->toBe(MaddraxikonContributionType::New)
        ->and($due->status)->toBe(MaddraxikonContributionStatus::Pending)
        ->and($due->minor)->toBeTrue()
        ->and($due->bot)->toBeFalse()
        ->and($due->tags)->toBe(['mw-new-redirect', 'visualeditor'])
        ->and($due->occurred_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($due->eligible_after)->toBeInstanceOf(CarbonInterface::class)
        ->and($due->checked_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($due->occurred_at_epoch)->toBe($due->occurred_at->getTimestamp())
        ->and($due->eligible_after_epoch)->toBe($due->eligible_after->getTimestamp())
        ->and($due->occurredAtUtc()->getTimestamp())->toBe($due->occurred_at_epoch)
        ->and($due->accountLink->is($link))->toBeTrue()
        ->and($due->user->is($link->user))->toBeTrue()
        ->and(MaddraxikonContribution::due()->pluck('id')->all())->toBe([$due->id])
        ->and(MaddraxikonContribution::due()->whereKey($future)->exists())->toBeFalse();
});

test('revision id is an idempotent import key per wiki', function (): void {
    $contribution = MaddraxikonContribution::factory()->create(['wiki_key' => 'maddraxikon-de', 'revision_id' => 123456]);
    expect(fn () => MaddraxikonContribution::factory()->create(['wiki_key' => $contribution->wiki_key, 'revision_id' => $contribution->revision_id]))->toThrow(QueryException::class);
});

test('recent change id is an idempotent import key per wiki', function (): void {
    $contribution = MaddraxikonContribution::factory()->create(['wiki_key' => 'maddraxikon-de', 'rc_id' => 654321]);
    expect(fn () => MaddraxikonContribution::factory()->create(['wiki_key' => $contribution->wiki_key, 'rc_id' => $contribution->rc_id]))->toThrow(QueryException::class);
});

test('recovered revisions may have no recent change id', function (): void {
    $first = MaddraxikonContribution::factory()->create(['rc_id' => null]);
    $second = MaddraxikonContribution::factory()->create(['rc_id' => null]);
    expect($first->fresh()->rc_id)->toBeNull()
        ->and($second->fresh()->rc_id)->toBeNull()
        ->and($second->revision_id)->not->toBe($first->revision_id);
});

test('same revision and rc ids may exist for a different wiki key', function (): void {
    $contribution = MaddraxikonContribution::factory()->create(['wiki_key' => 'maddraxikon-de', 'revision_id' => 2100, 'rc_id' => 2200]);
    $other = MaddraxikonContribution::factory()->create(['wiki_key' => 'another-wiki', 'revision_id' => $contribution->revision_id, 'rc_id' => $contribution->rc_id]);
    expect($other->id)->not->toBe($contribution->id);
});

test('reward event relates positive and negative point bookings', function (): void {
    $contribution = MaddraxikonContribution::factory()->create();
    $team = Team::membersTeam();
    expect($team)->not->toBeNull();
    $positive = UserPoint::create(['user_id' => $contribution->user_id, 'team_id' => $team->id, 'points' => 5]);
    $negative = UserPoint::create(['user_id' => $contribution->user_id, 'team_id' => $team->id, 'points' => -5]);
    $admin = User::factory()->create();
    $rule = BaxxEarningRule::where('action_key', MaddraxikonRewardEvent::ACTION_NEW_ARTICLE)->firstOrFail();
    $event = MaddraxikonRewardEvent::factory()->create(['source_contribution_id' => $contribution->id, 'account_link_id' => $contribution->account_link_id, 'user_id' => $contribution->user_id, 'action_key' => MaddraxikonRewardEvent::ACTION_NEW_ARTICLE, 'source_key' => 'new:'.$contribution->revision_id, 'baxx_earning_rule_id' => $rule->id, 'rule_points' => 5, 'rule_every_count' => 1, 'candidate_points' => 5, 'awarded_points' => 5, 'status' => MaddraxikonRewardEventStatus::Reversed, 'user_point_id' => $positive->id, 'reversal_user_point_id' => $negative->id, 'awarded_at' => now()->subHour(), 'reversed_at' => now(), 'reversed_by' => $admin->id, 'reversal_reason' => 'Nachträglich als Rücksetzung bestätigt.'])->fresh();
    expect($event->status)->toBe(MaddraxikonRewardEventStatus::Reversed)
        ->and($event->candidate_points)->toBe(5)
        ->and($event->awarded_points)->toBe(5)
        ->and($event->activity_date)->toBeInstanceOf(CarbonInterface::class)
        ->and($event->sourceContribution->is($contribution))->toBeTrue()
        ->and($event->earningRule->is($rule))->toBeTrue()
        ->and($event->userPoint->is($positive))->toBeTrue()
        ->and($event->reversalUserPoint->is($negative))->toBeTrue()
        ->and($event->reversedBy->is($admin))->toBeTrue()
        ->and($positive->maddraxikonRewardEvent->is($event))->toBeTrue()
        ->and($negative->maddraxikonReversalRewardEvent->is($event))->toBeTrue()
        ->and($rule->maddraxikonRewardEvents->contains($event))->toBeTrue();
});

test('reward source key and point links are unique', function (): void {
    $contribution = MaddraxikonContribution::factory()->create();
    $team = Team::membersTeam();
    expect($team)->not->toBeNull();
    $point = UserPoint::create(['user_id' => $contribution->user_id, 'team_id' => $team->id, 'points' => 1]);
    $event = MaddraxikonRewardEvent::factory()->create(['wiki_key' => 'maddraxikon-de', 'source_key' => 'edit-session:3456', 'user_point_id' => $point->id]);
    expect(fn () => MaddraxikonRewardEvent::factory()->create(['wiki_key' => $event->wiki_key, 'source_key' => $event->source_key, 'user_point_id' => $point->id]))->toThrow(QueryException::class);
});

test('deleting point booking preserves reward audit event', function (): void {
    $contribution = MaddraxikonContribution::factory()->create();
    $team = Team::membersTeam();
    expect($team)->not->toBeNull();
    $point = UserPoint::create(['user_id' => $contribution->user_id, 'team_id' => $team->id, 'points' => 1]);
    $event = MaddraxikonRewardEvent::factory()->create(['user_point_id' => $point->id]);
    $point->delete();
    $this->assertDatabaseHas('maddraxikon_reward_events', ['id' => $event->id, 'user_point_id' => null]);
});

test('deleting user cascades private maddraxikon data', function (): void {
    $link = MaddraxikonAccountLink::factory()->create();
    $contribution = MaddraxikonContribution::factory()->create(['account_link_id' => $link->id, 'user_id' => $link->user_id]);
    $event = MaddraxikonRewardEvent::factory()->create(['source_contribution_id' => $contribution->id, 'account_link_id' => $link->id, 'user_id' => $link->user_id]);
    $oauthSubject = $link->oauth_subject;
    $wikiUserId = $link->wiki_user_id;
    $link->user->delete();
    $this->assertDatabaseMissing('maddraxikon_account_links', ['id' => $link->id]);
    $this->assertDatabaseMissing('maddraxikon_contributions', ['id' => $contribution->id]);
    $this->assertDatabaseMissing('maddraxikon_reward_events', ['id' => $event->id]);
    $tombstone = MaddraxikonIdentityTombstone::query()->sole();
    expect($tombstone->oauth_subject_hash)->toBe(MaddraxikonIdentityTombstone::oauthSubjectHash($link->wiki_key, $oauthSubject))
        ->and($tombstone->wiki_user_id_hash)->toBe(MaddraxikonIdentityTombstone::wikiUserIdHash($link->wiki_key, $wikiUserId))
        ->and($tombstone->oauth_subject_hash)->not->toBe($oauthSubject)
        ->and(Schema::hasColumn('maddraxikon_identity_tombstones', 'wiki_username'))->toBeFalse();
});

test('sync state has portable defaults casts and unique wiki key', function (): void {
    $now = now();
    $state = MaddraxikonSyncState::create(['wiki_key' => 'maddraxikon-de', 'watermark_at' => $now, 'initial_watermark_at' => $now->copy()->subDay(), 'recovery_required_at' => $now, 'recovery_from_at' => $now->copy()->subHour(), 'recovery_until_at' => $now, 'last_recovery_succeeded_at' => $now, 'last_recovered_from_at' => $now->copy()->subHours(2), 'last_recovered_until_at' => $now->copy()->subHour()])->fresh();
    expect($state->watermark_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($state->initial_watermark_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($state->recovery_required_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($state->recovery_from_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($state->recovery_until_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($state->last_recovery_succeeded_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($state->last_recovered_from_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($state->last_recovered_until_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($state->consecutive_failures)->toBe(0)
        ->and($state->last_imported_count)->toBe(0)
        ->and($state->last_recovered_count)->toBe(0)
        ->and(fn () => MaddraxikonSyncState::create(['wiki_key' => $state->wiki_key]))->toThrow(QueryException::class);
});

test('sync instants remain distinct during fall dst overlap', function (): void {
    $firstInstant = CarbonImmutable::parse('2026-10-25T00:30:00Z');
    $secondInstant = CarbonImmutable::parse('2026-10-25T01:30:00Z');
    $first = MaddraxikonSyncState::create(['wiki_key' => 'first-dst-state', 'watermark_at' => $firstInstant, 'initial_watermark_at' => $firstInstant, 'recovery_required_at' => $firstInstant, 'recovery_from_at' => $firstInstant, 'recovery_until_at' => $firstInstant])->fresh();
    $second = MaddraxikonSyncState::create(['wiki_key' => 'second-dst-state', 'watermark_at' => $secondInstant, 'initial_watermark_at' => $secondInstant, 'recovery_required_at' => $secondInstant, 'recovery_from_at' => $secondInstant, 'recovery_until_at' => $secondInstant])->fresh();
    expect($second->watermark_at->setTimezone('Europe/Berlin')->format('Y-m-d H:i'))->toBe($first->watermark_at->setTimezone('Europe/Berlin')->format('Y-m-d H:i'))
        ->and($first->watermark_at_epoch)->toBe($firstInstant->timestamp)
        ->and($second->watermark_at_epoch)->toBe($secondInstant->timestamp)
        ->and($first->watermark_at->timestamp)->toBe($firstInstant->timestamp)
        ->and($second->watermark_at->timestamp)->toBe($secondInstant->timestamp)
        ->and($first->recovery_until_at->timestamp)->toBe($firstInstant->timestamp)
        ->and($second->recovery_until_at->timestamp)->toBe($secondInstant->timestamp)
        ->and($second->watermark_at_epoch)->not->toBe($first->watermark_at_epoch);
});

test('maddraxikon rules are seeded idempotently with expected defaults', function (): void {
    $seeder = app(BaxxEarningRuleSeeder::class);
    $seeder->run();
    $seeder->run();
    $this->assertDatabaseHas('baxx_earning_rules', ['action_key' => MaddraxikonRewardEvent::ACTION_EDIT_SESSION, 'points' => 1, 'every_count' => 5, 'is_active' => true]);
    $this->assertDatabaseHas('baxx_earning_rules', ['action_key' => MaddraxikonRewardEvent::ACTION_NEW_ARTICLE, 'points' => 5, 'every_count' => 1, 'is_active' => true]);
    expect(BaxxEarningRule::whereIn('action_key', [MaddraxikonRewardEvent::ACTION_EDIT_SESSION, MaddraxikonRewardEvent::ACTION_NEW_ARTICLE])->count())->toBe(2);
});

test('configuration uses fixed derived endpoints and safe defaults', function (): void {
    expect(config('maddraxikon.wiki_key'))->toBe('maddraxikon-de')
        ->and(config('maddraxikon.base_url'))->toBe('https://de.maddraxikon.com')
        ->and(config('maddraxikon.api_url'))->toBe('https://de.maddraxikon.com/api.php')
        ->and(config('maddraxikon.oauth.authorize_url'))->toBe('https://de.maddraxikon.com/rest.php/oauth2/authorize')
        ->and(config('maddraxikon.oauth.token_url'))->toBe('https://de.maddraxikon.com/rest.php/oauth2/access_token')
        ->and(config('maddraxikon.oauth.profile_url'))->toBe('https://de.maddraxikon.com/rest.php/oauth2/resource/profile')
        ->and(config('maddraxikon.allowed_namespaces'))->toBe([0, 10, 14, 102, 106, 108, 112, 420])
        ->and(config('maddraxikon.expected_namespace_names.112'))->toBe('SMW/Schema')
        ->and(config('maddraxikon.minimum_article_bytes'))->toBe(500)
        ->and(config('maddraxikon.session_window_minutes'))->toBe(30)
        ->and(config('maddraxikon.evaluation_delay_hours'))->toBe(24)
        ->and(config('maddraxikon.daily_point_cap'))->toBe(10)
        ->and(config('maddraxikon.timezone'))->toBe('Europe/Berlin')
        ->and(config('maddraxikon.sync.recent_changes_retention_days'))->toBe(30)
        ->and(config('maddraxikon.sync.recovery_max_window_days'))->toBe(90)
        ->and(config('maddraxikon.sync.usercontribs_batch_size'))->toBe(50)
        ->and(config('maddraxikon.features.linking_enabled'))->toBeFalse()
        ->and(config('maddraxikon.features.sync_enabled'))->toBeFalse()
        ->and(config('maddraxikon.features.awards_enabled'))->toBeFalse()
        ->and(config('services.maddraxikon.scope'))->toBe('mwoauth-authonly')
        ->and(config('services.maddraxikon.client_secret'))->toBeNull();
});
