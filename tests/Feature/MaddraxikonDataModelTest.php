<?php

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
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaddraxikonDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_maddraxikon_tables_and_ledger_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('maddraxikon_account_links', [
            'user_id',
            'wiki_key',
            'oauth_subject',
            'wiki_user_id',
            'wiki_username',
            'status',
            'first_verified_at',
            'first_verified_at_epoch',
            'verified_at',
            'verified_at_epoch',
            'disconnected_at',
            'disconnected_at_epoch',
            'consent_version',
            'consented_at',
            'consented_at_epoch',
        ]));
        $this->assertTrue(Schema::hasColumns('maddraxikon_identity_tombstones', [
            'wiki_key',
            'hash_key_version',
            'hash_key_fingerprint',
            'oauth_subject_hash',
            'wiki_user_id_hash',
            'retired_at',
        ]));
        $this->assertTrue(Schema::hasColumns('maddraxikon_contributions', [
            'rc_id',
            'revision_id',
            'page_id',
            'namespace_id',
            'account_link_id',
            'user_id',
            'tags',
            'occurred_at',
            'occurred_at_epoch',
            'eligible_after',
            'eligible_after_epoch',
            'status',
        ]));
        $this->assertTrue(Schema::hasColumns('maddraxikon_reward_events', [
            'source_key',
            'source_contribution_id',
            'source_revision_id',
            'baxx_earning_rule_id',
            'candidate_points',
            'awarded_points',
            'capped_points',
            'user_point_id',
            'reversal_user_point_id',
            'activity_pending',
            'reversed_by',
            'reversal_reason',
        ]));
        $this->assertTrue(Schema::hasColumns('maddraxikon_sync_states', [
            'wiki_key',
            'watermark_at',
            'watermark_at_epoch',
            'initial_watermark_at',
            'initial_watermark_at_epoch',
            'last_started_at',
            'last_started_at_epoch',
            'run_sequence',
            'last_succeeded_at',
            'last_succeeded_at_epoch',
            'last_error_at',
            'last_error_at_epoch',
            'last_error',
            'consecutive_failures',
            'last_imported_count',
            'last_seen_rc_id',
            'recovery_required_at',
            'recovery_required_at_epoch',
            'recovery_from_at',
            'recovery_from_at_epoch',
            'recovery_until_at',
            'recovery_until_at_epoch',
            'last_recovery_succeeded_at',
            'last_recovery_succeeded_at_epoch',
            'last_recovered_from_at',
            'last_recovered_from_at_epoch',
            'last_recovered_until_at',
            'last_recovered_until_at_epoch',
            'last_recovered_count',
        ]));
        $this->assertTrue(Schema::hasIndex(
            'maddraxikon_reward_events',
            'maddraxikon_rewards_user_action_sequence_index',
        ));
        $this->assertTrue(Schema::hasIndex(
            'maddraxikon_reward_events',
            'maddraxikon_rewards_pending_activity_index',
        ));

        // Statuses deliberately remain portable strings instead of DB enums.
        $this->assertSame('varchar', Schema::getColumnType('maddraxikon_account_links', 'status'));
        $this->assertSame('varchar', Schema::getColumnType('maddraxikon_contributions', 'status'));
        $this->assertSame('varchar', Schema::getColumnType('maddraxikon_reward_events', 'status'));
    }

    public function test_account_link_casts_scope_and_user_relations(): void
    {
        $user = User::factory()->create();
        $link = MaddraxikonAccountLink::factory()->create([
            'user_id' => $user->id,
            'wiki_user_id' => 7301,
        ])->fresh();
        $contribution = MaddraxikonContribution::factory()->create([
            'account_link_id' => $link->id,
            'user_id' => $user->id,
        ]);
        $rewardEvent = MaddraxikonRewardEvent::factory()->create([
            'source_contribution_id' => $contribution->id,
            'account_link_id' => $link->id,
            'user_id' => $user->id,
        ]);

        $this->assertSame(MaddraxikonAccountLinkStatus::Active, $link->status);
        $this->assertInstanceOf(CarbonInterface::class, $link->first_verified_at);
        $this->assertInstanceOf(CarbonInterface::class, $link->verified_at);
        $this->assertInstanceOf(CarbonInterface::class, $link->consented_at);
        $this->assertTrue($link->isActive());
        $this->assertSame([$link->id], MaddraxikonAccountLink::active()->pluck('id')->all());
        $this->assertTrue($link->user->is($user));
        $this->assertTrue($user->maddraxikonAccountLink->is($link));
        $this->assertTrue($user->maddraxikonContributions->contains($contribution));
        $this->assertTrue($user->maddraxikonRewardEvents->contains($rewardEvent));
        $this->assertTrue($link->contributions->contains($contribution));
        $this->assertTrue($link->rewardEvents->contains($rewardEvent));
    }

    public function test_disconnected_link_is_not_active(): void
    {
        $link = MaddraxikonAccountLink::factory()->disconnected()->create()->fresh();

        $this->assertSame(MaddraxikonAccountLinkStatus::Disconnected, $link->status);
        $this->assertInstanceOf(CarbonInterface::class, $link->disconnected_at);
        $this->assertFalse($link->isActive());
        $this->assertFalse(MaddraxikonAccountLink::active()->whereKey($link)->exists());
    }

    public function test_account_link_instants_remain_distinct_during_fall_dst_overlap(): void
    {
        $firstInstant = CarbonImmutable::parse('2026-10-25T00:30:00Z');
        $secondInstant = CarbonImmutable::parse('2026-10-25T01:30:00Z');

        $first = MaddraxikonAccountLink::factory()->create([
            'first_verified_at' => $firstInstant,
            'verified_at' => $firstInstant,
            'consented_at' => $firstInstant,
        ])->fresh();
        $second = MaddraxikonAccountLink::factory()->create([
            'first_verified_at' => $secondInstant,
            'verified_at' => $secondInstant,
            'consented_at' => $secondInstant,
        ])->fresh();

        $this->assertSame(
            $first->verified_at->setTimezone('Europe/Berlin')->format('Y-m-d H:i'),
            $second->verified_at->setTimezone('Europe/Berlin')->format('Y-m-d H:i')
        );
        $this->assertSame($firstInstant->timestamp, $first->verified_at_epoch);
        $this->assertSame($secondInstant->timestamp, $second->verified_at_epoch);
        $this->assertSame($firstInstant->timestamp, $first->verified_at->timestamp);
        $this->assertSame($secondInstant->timestamp, $second->verified_at->timestamp);
        $this->assertNotSame($first->verified_at_epoch, $second->verified_at_epoch);

        $first->update(['disconnected_at' => $firstInstant]);
        $second->update(['disconnected_at' => $secondInstant]);

        $this->assertSame(
            $firstInstant->timestamp,
            $first->fresh()->disconnected_at->timestamp
        );
        $this->assertSame(
            $secondInstant->timestamp,
            $second->fresh()->disconnected_at->timestamp
        );
    }

    public function test_one_club_user_cannot_have_two_historical_links(): void
    {
        $link = MaddraxikonAccountLink::factory()->disconnected()->create();

        $this->expectException(QueryException::class);

        MaddraxikonAccountLink::factory()->create([
            'user_id' => $link->user_id,
        ]);
    }

    public function test_oauth_subject_cannot_be_reclaimed_after_disconnect(): void
    {
        $link = MaddraxikonAccountLink::factory()->disconnected()->create([
            'oauth_subject' => 'opaque-subject-that-is-not-a-username',
        ]);

        $this->expectException(QueryException::class);

        MaddraxikonAccountLink::factory()->create([
            'oauth_subject' => $link->oauth_subject,
        ]);
    }

    public function test_local_wiki_user_id_cannot_be_reclaimed_after_disconnect(): void
    {
        $link = MaddraxikonAccountLink::factory()->disconnected()->create([
            'wiki_user_id' => 991_100,
        ]);

        $this->expectException(QueryException::class);

        MaddraxikonAccountLink::factory()->create([
            'wiki_user_id' => $link->wiki_user_id,
        ]);
    }

    public function test_contribution_casts_due_scope_and_relations(): void
    {
        $link = MaddraxikonAccountLink::factory()->create();
        $due = MaddraxikonContribution::factory()->create([
            'account_link_id' => $link->id,
            'user_id' => $link->user_id,
            'type' => MaddraxikonContributionType::New,
            'status' => MaddraxikonContributionStatus::Pending,
            'minor' => 1,
            'tags' => ['mw-new-redirect', 'visualeditor'],
            'eligible_after' => now()->subSecond(),
            'checked_at' => now(),
        ])->fresh();
        $future = MaddraxikonContribution::factory()->create([
            'eligible_after' => now()->addMinute(),
        ]);
        MaddraxikonContribution::factory()->create([
            'eligible_after' => now()->subMinute(),
            'status' => MaddraxikonContributionStatus::Rejected,
        ]);

        $this->assertSame(MaddraxikonContributionType::New, $due->type);
        $this->assertSame(MaddraxikonContributionStatus::Pending, $due->status);
        $this->assertTrue($due->minor);
        $this->assertFalse($due->bot);
        $this->assertSame(['mw-new-redirect', 'visualeditor'], $due->tags);
        $this->assertInstanceOf(CarbonInterface::class, $due->occurred_at);
        $this->assertInstanceOf(CarbonInterface::class, $due->eligible_after);
        $this->assertInstanceOf(CarbonInterface::class, $due->checked_at);
        $this->assertSame(
            $due->occurred_at->getTimestamp(),
            $due->occurred_at_epoch
        );
        $this->assertSame(
            $due->eligible_after->getTimestamp(),
            $due->eligible_after_epoch
        );
        $this->assertSame($due->occurred_at_epoch, $due->occurredAtUtc()->getTimestamp());
        $this->assertTrue($due->accountLink->is($link));
        $this->assertTrue($due->user->is($link->user));
        $this->assertSame([$due->id], MaddraxikonContribution::due()->pluck('id')->all());
        $this->assertFalse(MaddraxikonContribution::due()->whereKey($future)->exists());
    }

    public function test_revision_id_is_an_idempotent_import_key_per_wiki(): void
    {
        $contribution = MaddraxikonContribution::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'revision_id' => 123_456,
        ]);

        $this->expectException(QueryException::class);

        MaddraxikonContribution::factory()->create([
            'wiki_key' => $contribution->wiki_key,
            'revision_id' => $contribution->revision_id,
        ]);
    }

    public function test_recent_change_id_is_an_idempotent_import_key_per_wiki(): void
    {
        $contribution = MaddraxikonContribution::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'rc_id' => 654_321,
        ]);

        $this->expectException(QueryException::class);

        MaddraxikonContribution::factory()->create([
            'wiki_key' => $contribution->wiki_key,
            'rc_id' => $contribution->rc_id,
        ]);
    }

    public function test_recovered_revisions_may_have_no_recent_change_id(): void
    {
        $first = MaddraxikonContribution::factory()->create([
            'rc_id' => null,
        ]);
        $second = MaddraxikonContribution::factory()->create([
            'rc_id' => null,
        ]);

        $this->assertNull($first->fresh()->rc_id);
        $this->assertNull($second->fresh()->rc_id);
        $this->assertNotSame($first->revision_id, $second->revision_id);
    }

    public function test_same_revision_and_rc_ids_may_exist_for_a_different_wiki_key(): void
    {
        $contribution = MaddraxikonContribution::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'revision_id' => 2100,
            'rc_id' => 2200,
        ]);

        $other = MaddraxikonContribution::factory()->create([
            'wiki_key' => 'another-wiki',
            'revision_id' => $contribution->revision_id,
            'rc_id' => $contribution->rc_id,
        ]);

        $this->assertNotSame($contribution->id, $other->id);
    }

    public function test_reward_event_relates_positive_and_negative_point_bookings(): void
    {
        $contribution = MaddraxikonContribution::factory()->create();
        $team = Team::membersTeam();
        $this->assertNotNull($team);
        $positive = UserPoint::create([
            'user_id' => $contribution->user_id,
            'team_id' => $team->id,
            'points' => 5,
        ]);
        $negative = UserPoint::create([
            'user_id' => $contribution->user_id,
            'team_id' => $team->id,
            'points' => -5,
        ]);
        $admin = User::factory()->create();
        $rule = BaxxEarningRule::where('action_key', MaddraxikonRewardEvent::ACTION_NEW_ARTICLE)->firstOrFail();

        $event = MaddraxikonRewardEvent::factory()->create([
            'source_contribution_id' => $contribution->id,
            'account_link_id' => $contribution->account_link_id,
            'user_id' => $contribution->user_id,
            'action_key' => MaddraxikonRewardEvent::ACTION_NEW_ARTICLE,
            'source_key' => 'new:'.$contribution->revision_id,
            'baxx_earning_rule_id' => $rule->id,
            'rule_points' => 5,
            'rule_every_count' => 1,
            'candidate_points' => 5,
            'awarded_points' => 5,
            'status' => MaddraxikonRewardEventStatus::Reversed,
            'user_point_id' => $positive->id,
            'reversal_user_point_id' => $negative->id,
            'awarded_at' => now()->subHour(),
            'reversed_at' => now(),
            'reversed_by' => $admin->id,
            'reversal_reason' => 'Nachträglich als Rücksetzung bestätigt.',
        ])->fresh();

        $this->assertSame(MaddraxikonRewardEventStatus::Reversed, $event->status);
        $this->assertSame(5, $event->candidate_points);
        $this->assertSame(5, $event->awarded_points);
        $this->assertInstanceOf(CarbonInterface::class, $event->activity_date);
        $this->assertTrue($event->sourceContribution->is($contribution));
        $this->assertTrue($event->earningRule->is($rule));
        $this->assertTrue($event->userPoint->is($positive));
        $this->assertTrue($event->reversalUserPoint->is($negative));
        $this->assertTrue($event->reversedBy->is($admin));
        $this->assertTrue($positive->maddraxikonRewardEvent->is($event));
        $this->assertTrue($negative->maddraxikonReversalRewardEvent->is($event));
        $this->assertTrue($rule->maddraxikonRewardEvents->contains($event));
    }

    public function test_reward_source_key_and_point_links_are_unique(): void
    {
        $contribution = MaddraxikonContribution::factory()->create();
        $team = Team::membersTeam();
        $this->assertNotNull($team);
        $point = UserPoint::create([
            'user_id' => $contribution->user_id,
            'team_id' => $team->id,
            'points' => 1,
        ]);
        $event = MaddraxikonRewardEvent::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'source_key' => 'edit-session:3456',
            'user_point_id' => $point->id,
        ]);

        $this->expectException(QueryException::class);

        MaddraxikonRewardEvent::factory()->create([
            'wiki_key' => $event->wiki_key,
            'source_key' => $event->source_key,
            'user_point_id' => $point->id,
        ]);
    }

    public function test_deleting_point_booking_preserves_reward_audit_event(): void
    {
        $contribution = MaddraxikonContribution::factory()->create();
        $team = Team::membersTeam();
        $this->assertNotNull($team);
        $point = UserPoint::create([
            'user_id' => $contribution->user_id,
            'team_id' => $team->id,
            'points' => 1,
        ]);
        $event = MaddraxikonRewardEvent::factory()->create([
            'user_point_id' => $point->id,
        ]);

        $point->delete();

        $this->assertDatabaseHas('maddraxikon_reward_events', [
            'id' => $event->id,
            'user_point_id' => null,
        ]);
    }

    public function test_deleting_user_cascades_private_maddraxikon_data(): void
    {
        $link = MaddraxikonAccountLink::factory()->create();
        $contribution = MaddraxikonContribution::factory()->create([
            'account_link_id' => $link->id,
            'user_id' => $link->user_id,
        ]);
        $event = MaddraxikonRewardEvent::factory()->create([
            'source_contribution_id' => $contribution->id,
            'account_link_id' => $link->id,
            'user_id' => $link->user_id,
        ]);

        $oauthSubject = $link->oauth_subject;
        $wikiUserId = $link->wiki_user_id;
        $link->user->delete();

        $this->assertDatabaseMissing('maddraxikon_account_links', ['id' => $link->id]);
        $this->assertDatabaseMissing('maddraxikon_contributions', ['id' => $contribution->id]);
        $this->assertDatabaseMissing('maddraxikon_reward_events', ['id' => $event->id]);

        $tombstone = MaddraxikonIdentityTombstone::query()->sole();
        $this->assertSame(
            MaddraxikonIdentityTombstone::oauthSubjectHash($link->wiki_key, $oauthSubject),
            $tombstone->oauth_subject_hash,
        );
        $this->assertSame(
            MaddraxikonIdentityTombstone::wikiUserIdHash($link->wiki_key, $wikiUserId),
            $tombstone->wiki_user_id_hash,
        );
        $this->assertNotSame($oauthSubject, $tombstone->oauth_subject_hash);
        $this->assertFalse(Schema::hasColumn('maddraxikon_identity_tombstones', 'wiki_username'));
    }

    public function test_sync_state_has_portable_defaults_casts_and_unique_wiki_key(): void
    {
        $now = now();
        $state = MaddraxikonSyncState::create([
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => $now,
            'initial_watermark_at' => $now->copy()->subDay(),
            'recovery_required_at' => $now,
            'recovery_from_at' => $now->copy()->subHour(),
            'recovery_until_at' => $now,
            'last_recovery_succeeded_at' => $now,
            'last_recovered_from_at' => $now->copy()->subHours(2),
            'last_recovered_until_at' => $now->copy()->subHour(),
        ])->fresh();

        $this->assertInstanceOf(CarbonInterface::class, $state->watermark_at);
        $this->assertInstanceOf(CarbonInterface::class, $state->initial_watermark_at);
        $this->assertInstanceOf(CarbonInterface::class, $state->recovery_required_at);
        $this->assertInstanceOf(CarbonInterface::class, $state->recovery_from_at);
        $this->assertInstanceOf(CarbonInterface::class, $state->recovery_until_at);
        $this->assertInstanceOf(CarbonInterface::class, $state->last_recovery_succeeded_at);
        $this->assertInstanceOf(CarbonInterface::class, $state->last_recovered_from_at);
        $this->assertInstanceOf(CarbonInterface::class, $state->last_recovered_until_at);
        $this->assertSame(0, $state->consecutive_failures);
        $this->assertSame(0, $state->last_imported_count);
        $this->assertSame(0, $state->last_recovered_count);

        $this->expectException(QueryException::class);

        MaddraxikonSyncState::create(['wiki_key' => $state->wiki_key]);
    }

    public function test_sync_instants_remain_distinct_during_fall_dst_overlap(): void
    {
        $firstInstant = CarbonImmutable::parse('2026-10-25T00:30:00Z');
        $secondInstant = CarbonImmutable::parse('2026-10-25T01:30:00Z');

        $first = MaddraxikonSyncState::create([
            'wiki_key' => 'first-dst-state',
            'watermark_at' => $firstInstant,
            'initial_watermark_at' => $firstInstant,
            'recovery_required_at' => $firstInstant,
            'recovery_from_at' => $firstInstant,
            'recovery_until_at' => $firstInstant,
        ])->fresh();
        $second = MaddraxikonSyncState::create([
            'wiki_key' => 'second-dst-state',
            'watermark_at' => $secondInstant,
            'initial_watermark_at' => $secondInstant,
            'recovery_required_at' => $secondInstant,
            'recovery_from_at' => $secondInstant,
            'recovery_until_at' => $secondInstant,
        ])->fresh();

        $this->assertSame(
            $first->watermark_at->setTimezone('Europe/Berlin')->format('Y-m-d H:i'),
            $second->watermark_at->setTimezone('Europe/Berlin')->format('Y-m-d H:i')
        );
        $this->assertSame($firstInstant->timestamp, $first->watermark_at_epoch);
        $this->assertSame($secondInstant->timestamp, $second->watermark_at_epoch);
        $this->assertSame($firstInstant->timestamp, $first->watermark_at->timestamp);
        $this->assertSame($secondInstant->timestamp, $second->watermark_at->timestamp);
        $this->assertSame(
            $firstInstant->timestamp,
            $first->recovery_until_at->timestamp
        );
        $this->assertSame(
            $secondInstant->timestamp,
            $second->recovery_until_at->timestamp
        );
        $this->assertNotSame($first->watermark_at_epoch, $second->watermark_at_epoch);
    }

    public function test_maddraxikon_rules_are_seeded_idempotently_with_expected_defaults(): void
    {
        $seeder = app(BaxxEarningRuleSeeder::class);
        $seeder->run();
        $seeder->run();

        $this->assertDatabaseHas('baxx_earning_rules', [
            'action_key' => MaddraxikonRewardEvent::ACTION_EDIT_SESSION,
            'points' => 1,
            'every_count' => 5,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('baxx_earning_rules', [
            'action_key' => MaddraxikonRewardEvent::ACTION_NEW_ARTICLE,
            'points' => 5,
            'every_count' => 1,
            'is_active' => true,
        ]);
        $this->assertSame(
            2,
            BaxxEarningRule::whereIn('action_key', [
                MaddraxikonRewardEvent::ACTION_EDIT_SESSION,
                MaddraxikonRewardEvent::ACTION_NEW_ARTICLE,
            ])->count()
        );
    }

    public function test_configuration_uses_fixed_derived_endpoints_and_safe_defaults(): void
    {
        $this->assertSame('maddraxikon-de', config('maddraxikon.wiki_key'));
        $this->assertSame('https://de.maddraxikon.com', config('maddraxikon.base_url'));
        $this->assertSame('https://de.maddraxikon.com/api.php', config('maddraxikon.api_url'));
        $this->assertSame(
            'https://de.maddraxikon.com/rest.php/oauth2/authorize',
            config('maddraxikon.oauth.authorize_url')
        );
        $this->assertSame(
            'https://de.maddraxikon.com/rest.php/oauth2/access_token',
            config('maddraxikon.oauth.token_url')
        );
        $this->assertSame(
            'https://de.maddraxikon.com/rest.php/oauth2/resource/profile',
            config('maddraxikon.oauth.profile_url')
        );
        $this->assertSame([0, 10, 14, 102, 106, 108, 112, 420], config('maddraxikon.allowed_namespaces'));
        $this->assertSame('SMW/Schema', config('maddraxikon.expected_namespace_names.112'));
        $this->assertSame(500, config('maddraxikon.minimum_article_bytes'));
        $this->assertSame(30, config('maddraxikon.session_window_minutes'));
        $this->assertSame(24, config('maddraxikon.evaluation_delay_hours'));
        $this->assertSame(10, config('maddraxikon.daily_point_cap'));
        $this->assertSame('Europe/Berlin', config('maddraxikon.timezone'));
        $this->assertSame(30, config('maddraxikon.sync.recent_changes_retention_days'));
        $this->assertSame(90, config('maddraxikon.sync.recovery_max_window_days'));
        $this->assertSame(50, config('maddraxikon.sync.usercontribs_batch_size'));
        $this->assertFalse(config('maddraxikon.features.linking_enabled'));
        $this->assertFalse(config('maddraxikon.features.sync_enabled'));
        $this->assertFalse(config('maddraxikon.features.awards_enabled'));
        $this->assertSame('mwoauth-authonly', config('services.maddraxikon.scope'));
        $this->assertNull(config('services.maddraxikon.client_secret'));
    }
}
