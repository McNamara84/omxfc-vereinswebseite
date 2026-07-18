<?php

namespace Tests\Feature;

use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonContributionType;
use App\Enums\Role;
use App\Exceptions\MaddraxikonApiException;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use App\Services\Maddraxikon\MaddraxikonContributionImporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaddraxikonContributionImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Team::clearMembersTeamCache();

        if (! Team::query()->where('name', 'Mitglieder')->exists()) {
            Team::factory()->create([
                'name' => 'Mitglieder',
                'personal_team' => false,
            ]);
        }
        Team::clearMembersTeamCache();

        config([
            'maddraxikon.features.sync_enabled' => true,
            'maddraxikon.wiki_key' => 'maddraxikon-de',
            'maddraxikon.allowed_namespaces' => [0, 10, 14, 102, 106, 108, 112, 420],
            'maddraxikon.session_window_minutes' => 30,
            'maddraxikon.evaluation_delay_hours' => 24,
            'maddraxikon.sync.overlap_minutes' => 10,
            'maddraxikon.sync.recent_changes_retention_days' => 30,
            'maddraxikon.sync.max_window_minutes' => 360,
            'maddraxikon.sync.recovery_max_window_days' => 90,
        ]);
    }

    public function test_first_run_sets_no_backfill_watermark_without_calling_the_api(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);

        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock->shouldNotReceive('recentChanges')
        );

        $this->assertSame(0, app(MaddraxikonContributionImporter::class)->sync());

        $this->assertDatabaseHas('maddraxikon_sync_states', [
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => $now->format('Y-m-d H:i:s'),
            'watermark_at_epoch' => $now->timestamp,
            'initial_watermark_at' => $now->format('Y-m-d H:i:s'),
            'initial_watermark_at_epoch' => $now->timestamp,
            'last_succeeded_at' => $now->format('Y-m-d H:i:s'),
            'last_succeeded_at_epoch' => $now->timestamp,
            'last_imported_count' => 0,
        ]);
        $this->assertDatabaseCount('maddraxikon_contributions', 0);
    }

    public function test_backlog_is_advanced_in_bounded_windows_without_appearing_stale(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        $watermark = $now->subHours(12);
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => $watermark,
            'last_succeeded_at' => $watermark,
        ]);

        $expectedFrom = $watermark->subMinutes(10);
        $expectedUntil = $watermark->addHours(6);
        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('recentChanges')
                ->once()
                ->withArgs(fn (
                    mixed $actualFrom,
                    mixed $actualUntil
                ): bool => $actualFrom->equalTo($expectedFrom)
                    && $actualUntil->equalTo($expectedUntil))
                ->andReturn([])
        );

        $this->assertSame(
            0,
            app(MaddraxikonContributionImporter::class)->sync()
        );

        $state = MaddraxikonSyncState::query()
            ->where('wiki_key', 'maddraxikon-de')
            ->firstOrFail();
        $this->assertSame(
            $expectedUntil->timestamp,
            $state->watermark_at->timestamp
        );
        $this->assertSame(
            $now->timestamp,
            $state->last_succeeded_at->timestamp
        );
    }

    public function test_sync_imports_only_linked_eligible_changes_and_builds_edit_sessions(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);

        $link = MaddraxikonAccountLink::factory()->create([
            'wiki_user_id' => 55,
            'wiki_username' => 'Alter Name',
            'verified_at' => $now->subDays(2)->setTimezone(config('app.timezone')),
        ]);
        $this->makeEligible($link);

        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => $now->subHours(3)->setTimezone(config('app.timezone')),
        ]);

        $changes = [
            $this->change(1, 101, 55, 'Neuer Name', '2026-07-18T08:00:00Z'),
            $this->change(2, 102, 55, 'Neuer Name', '2026-07-18T08:30:00Z'),
            $this->change(3, 103, 55, 'Neuer Name', '2026-07-18T09:00:01Z'),
            $this->change(
                4,
                104,
                55,
                'Neuer Name',
                '2026-07-18T09:10:00Z',
                type: 'new',
                pageId: 999,
                oldRevisionId: 0,
                oldSize: 0,
                newSize: 700,
            ),
            // Not linked.
            $this->change(5, 105, 999, 'Fremd', '2026-07-18T09:20:00Z'),
            // Explicitly excluded namespace.
            $this->change(
                6,
                106,
                55,
                'Neuer Name',
                '2026-07-18T09:30:00Z',
                namespaceId: 1,
            ),
            // Defense in depth despite rcshow.
            [
                ...$this->change(7, 107, 55, 'Neuer Name', '2026-07-18T09:40:00Z'),
                'bot' => true,
            ],
        ];

        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('recentChanges')
                ->once()
                ->andReturn($changes)
        );

        $this->assertSame(4, app(MaddraxikonContributionImporter::class)->sync());
        $this->assertDatabaseCount('maddraxikon_contributions', 4);

        $first = MaddraxikonContribution::query()->where('revision_id', 101)->firstOrFail();
        $second = MaddraxikonContribution::query()->where('revision_id', 102)->firstOrFail();
        $third = MaddraxikonContribution::query()->where('revision_id', 103)->firstOrFail();
        $newArticle = MaddraxikonContribution::query()->where('revision_id', 104)->firstOrFail();

        $this->assertSame(101, $first->session_anchor_revision_id);
        $this->assertSame(101, $second->session_anchor_revision_id);
        $this->assertSame(103, $third->session_anchor_revision_id);
        $this->assertSame(
            '2026-07-19 08:30:00',
            $first->eligible_after->utc()->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            $first->eligible_after->timestamp,
            $second->eligible_after->timestamp
        );
        $this->assertNull($newArticle->session_anchor_revision_id);
        $this->assertSame(MaddraxikonContributionType::New, $newArticle->type);
        $this->assertSame(MaddraxikonContributionStatus::Pending, $newArticle->status);
        $this->assertSame('Neuer Name', $link->fresh()->wiki_username);

        $state = MaddraxikonSyncState::query()->where('wiki_key', 'maddraxikon-de')->firstOrFail();
        $this->assertSame($now->timestamp, $state->watermark_at->timestamp);
        $this->assertSame(4, $state->last_imported_count);
        $this->assertSame(7, $state->last_seen_rc_id);
    }

    public function test_hidden_and_unattributable_changes_do_not_block_the_batch(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);

        $link = MaddraxikonAccountLink::factory()->create([
            'wiki_user_id' => 55,
            'wiki_username' => 'Verified Name',
            'verified_at' => $now->subDay()->setTimezone(config('app.timezone')),
        ]);
        $this->makeEligible($link);

        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => $now->subHours(3)->setTimezone(config('app.timezone')),
        ]);

        $hidden = $this->change(
            10,
            110,
            55,
            'Hidden',
            '2026-07-18T10:00:00Z'
        );
        unset($hidden['user'], $hidden['userid']);
        $hidden['userhidden'] = true;

        $legacyUsernameOnly = $this->change(
            11,
            111,
            55,
            'Verified Name',
            '2026-07-18T10:05:00Z'
        );
        unset($legacyUsernameOnly['userid']);

        $missingUsername = $this->change(
            12,
            112,
            55,
            'Missing',
            '2026-07-18T10:10:00Z'
        );
        unset($missingUsername['user']);

        $visible = $this->change(
            13,
            113,
            55,
            'Verified Name',
            '2026-07-18T10:15:00Z'
        );

        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('recentChanges')
                ->once()
                ->andReturn([
                    $hidden,
                    $legacyUsernameOnly,
                    $missingUsername,
                    $visible,
                ])
        );

        $this->assertSame(1, app(MaddraxikonContributionImporter::class)->sync());
        $this->assertDatabaseHas('maddraxikon_contributions', [
            'revision_id' => 113,
            'wiki_user_id' => 55,
        ]);
        $this->assertDatabaseMissing('maddraxikon_contributions', [
            'revision_id' => 110,
        ]);
        $this->assertDatabaseMissing('maddraxikon_contributions', [
            'revision_id' => 111,
        ]);
        $this->assertDatabaseMissing('maddraxikon_contributions', [
            'revision_id' => 112,
        ]);
        $this->assertSame(
            13,
            MaddraxikonSyncState::query()->firstOrFail()->last_seen_rc_id
        );
    }

    public function test_active_links_of_non_members_and_applicants_are_not_imported(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);

        $membersTeam = Team::membersTeam();
        $this->assertNotNull($membersTeam);

        MaddraxikonAccountLink::factory()->create([
            'wiki_user_id' => 55,
            'verified_at' => $now->subDay()->setTimezone(config('app.timezone')),
        ]);
        $applicantLink = MaddraxikonAccountLink::factory()->create([
            'wiki_user_id' => 56,
            'verified_at' => $now->subDay()->setTimezone(config('app.timezone')),
        ]);
        $membersTeam->users()->attach($applicantLink->user_id, [
            'role' => Role::Anwaerter->value,
        ]);

        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => $now->subHours(3)->setTimezone(config('app.timezone')),
        ]);

        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('recentChanges')
                ->once()
                ->andReturn([
                    $this->change(1, 101, 55, 'Nonmember', '2026-07-18T10:00:00Z'),
                    $this->change(2, 102, 56, 'Applicant', '2026-07-18T10:05:00Z'),
                ])
        );

        $this->assertSame(0, app(MaddraxikonContributionImporter::class)->sync());
        $this->assertDatabaseCount('maddraxikon_contributions', 0);
    }

    public function test_every_configured_namespace_is_imported(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);

        $link = MaddraxikonAccountLink::factory()->create([
            'wiki_user_id' => 55,
            'verified_at' => $now->subDay()->setTimezone(config('app.timezone')),
        ]);
        $this->makeEligible($link);

        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => $now->subHours(3)->setTimezone(config('app.timezone')),
        ]);

        $allowedNamespaces = config('maddraxikon.allowed_namespaces');
        $changes = [];

        foreach ($allowedNamespaces as $index => $namespaceId) {
            $changes[] = $this->change(
                $index + 1,
                $index + 101,
                55,
                'Wiki',
                '2026-07-18T10:00:00Z',
                pageId: $index + 500,
                namespaceId: $namespaceId,
            );
        }

        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('recentChanges')
                ->once()
                ->andReturn($changes)
        );

        $this->assertSame(
            count($allowedNamespaces),
            app(MaddraxikonContributionImporter::class)->sync()
        );
        $this->assertSame(
            $allowedNamespaces,
            MaddraxikonContribution::query()
                ->orderBy('namespace_id')
                ->pluck('namespace_id')
                ->all()
        );
    }

    public function test_session_rebuild_does_not_cross_a_later_verification_boundary(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        $oldOccurredAt = $now->subHours(2);
        $verifiedAt = $oldOccurredAt->addMinutes(10);

        $link = MaddraxikonAccountLink::factory()->create([
            'wiki_user_id' => 55,
            'wiki_username' => 'Wiki',
            'first_verified_at' => $now->subMonth()->setTimezone(config('app.timezone')),
            'verified_at' => $verifiedAt->setTimezone(config('app.timezone')),
        ]);
        $this->makeEligible($link);

        $oldEligibleAfter = $oldOccurredAt->addDay();
        $oldContribution = MaddraxikonContribution::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'rc_id' => 90,
            'revision_id' => 90,
            'parent_revision_id' => 89,
            'page_id' => 500,
            'wiki_user_id' => 55,
            'wiki_username' => 'Wiki',
            'account_link_id' => $link->id,
            'user_id' => $link->user_id,
            'type' => MaddraxikonContributionType::Edit,
            'occurred_at' => $oldOccurredAt->setTimezone(config('app.timezone')),
            'session_anchor_revision_id' => 90,
            'eligible_after' => $oldEligibleAfter->setTimezone(config('app.timezone')),
        ]);

        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => $now->subHours(3)->setTimezone(config('app.timezone')),
        ]);

        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('recentChanges')
                ->once()
                ->andReturn([
                    $this->change(101, 101, 55, 'Wiki', '2026-07-18T10:20:00Z'),
                ])
        );

        $this->assertSame(1, app(MaddraxikonContributionImporter::class)->sync());

        $newContribution = MaddraxikonContribution::query()
            ->where('revision_id', 101)
            ->firstOrFail();

        $this->assertSame(90, $oldContribution->fresh()->session_anchor_revision_id);
        $this->assertSame(
            $oldEligibleAfter->timestamp,
            $oldContribution->fresh()->eligible_after->timestamp
        );
        $this->assertSame(101, $newContribution->session_anchor_revision_id);
    }

    public function test_changes_before_the_current_verification_period_are_not_imported(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);

        $link = MaddraxikonAccountLink::factory()->create([
            'wiki_user_id' => 55,
            'verified_at' => $now->subHour()->setTimezone(config('app.timezone')),
        ]);
        $this->makeEligible($link);
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => $now->subHours(3)->setTimezone(config('app.timezone')),
        ]);

        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('recentChanges')
                ->once()
                ->andReturn([
                    $this->change(1, 101, 55, 'Wiki', '2026-07-18T10:00:00Z'),
                ])
        );

        $this->assertSame(0, app(MaddraxikonContributionImporter::class)->sync());
        $this->assertDatabaseCount('maddraxikon_contributions', 0);
    }

    public function test_overlap_replay_is_idempotent(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);

        $link = MaddraxikonAccountLink::factory()->create([
            'wiki_user_id' => 55,
            'verified_at' => $now->subDay()->setTimezone(config('app.timezone')),
        ]);
        $this->makeEligible($link);
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => $now->subHour()->setTimezone(config('app.timezone')),
        ]);

        $change = $this->change(1, 101, 55, 'Wiki', '2026-07-18T11:30:00Z');

        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('recentChanges')
                ->twice()
                ->andReturn([$change])
        );

        $importer = app(MaddraxikonContributionImporter::class);

        $this->assertSame(1, $importer->sync());

        $this->travelTo($now->addMinutes(15));
        $this->assertSame(0, $importer->sync());
        $this->assertDatabaseCount('maddraxikon_contributions', 1);
    }

    public function test_slower_overlapping_sync_cannot_regress_the_watermark(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        $newerWatermark = $now->addMinute();
        $state = MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => $now->subHour(),
            'last_imported_count' => 9,
        ]);

        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('recentChanges')
                ->once()
                ->andReturnUsing(function () use (
                    $newerWatermark,
                    $state
                ): array {
                    $state->update([
                        'watermark_at' => $newerWatermark,
                        'last_succeeded_at' => $newerWatermark,
                        'last_imported_count' => 9,
                    ]);

                    return [];
                })
        );

        $this->assertSame(
            0,
            app(MaddraxikonContributionImporter::class)->sync()
        );

        $state->refresh();
        $this->assertSame(
            $newerWatermark->timestamp,
            $state->watermark_at->timestamp
        );
        $this->assertSame(9, $state->last_imported_count);
    }

    #[DataProvider('dstSessionCases')]
    public function test_edit_sessions_use_unambiguous_utc_instants_across_dst(
        string $nowIso,
        string $firstIso,
        string $secondIso
    ): void {
        $now = CarbonImmutable::parse($nowIso);
        $firstAt = CarbonImmutable::parse($firstIso);
        $secondAt = CarbonImmutable::parse($secondIso);
        $this->travelTo($now);
        $link = MaddraxikonAccountLink::factory()->create([
            'wiki_user_id' => 55,
            'verified_at' => $now->subDay(),
        ]);
        $this->makeEligible($link);
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => $now->subHours(3),
        ]);
        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('recentChanges')
                ->once()
                ->andReturn([
                    $this->change(1, 101, 55, 'Wiki', $firstIso),
                    $this->change(2, 102, 55, 'Wiki', $secondIso),
                ])
        );

        $this->assertSame(
            2,
            app(MaddraxikonContributionImporter::class)->sync()
        );

        $first = MaddraxikonContribution::query()
            ->where('revision_id', 101)
            ->sole();
        $second = MaddraxikonContribution::query()
            ->where('revision_id', 102)
            ->sole();

        $this->assertSame($firstAt->timestamp, $first->occurred_at_epoch);
        $this->assertSame($secondAt->timestamp, $second->occurred_at_epoch);
        $this->assertSame(101, $first->session_anchor_revision_id);
        $this->assertSame(101, $second->session_anchor_revision_id);
        $this->assertSame(
            $secondAt->addHours(24)->timestamp,
            $first->eligible_after_epoch
        );
        $this->assertSame(
            $first->eligible_after_epoch,
            $second->eligible_after_epoch
        );
    }

    public static function dstSessionCases(): array
    {
        return [
            'spring forward' => [
                '2026-03-29T04:00:00Z',
                '2026-03-29T00:50:00Z',
                '2026-03-29T01:10:00Z',
            ],
            'fall back' => [
                '2026-10-25T04:00:00Z',
                '2026-10-25T00:50:00Z',
                '2026-10-25T01:10:00Z',
            ],
        ];
    }

    public function test_expired_recent_changes_window_opens_and_extends_recovery_alarm(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        $watermark = $now->subDays(31);

        $state = MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'initial_watermark_at' => $now->subDays(40)->setTimezone(config('app.timezone')),
            'watermark_at' => $watermark->setTimezone(config('app.timezone')),
            'consecutive_failures' => 0,
        ]);

        $this->mock(
            MaddraxikonApiClient::class,
            function (MockInterface $mock): void {
                $mock->shouldNotReceive('recentChanges');
                $mock->shouldNotReceive('userContributions');
            }
        );

        $importer = app(MaddraxikonContributionImporter::class);
        $this->assertSame(0, $importer->sync());

        $state->refresh();
        $this->assertSame($watermark->timestamp, $state->watermark_at->timestamp);
        $this->assertSame($watermark->timestamp, $state->recovery_from_at->timestamp);
        $this->assertSame($now->timestamp, $state->recovery_until_at->timestamp);
        $this->assertSame(1, $state->consecutive_failures);
        $this->assertStringContainsString(
            'Watermark wird nicht übersprungen',
            $state->last_error
        );

        $this->travelTo($now->addHour());
        $this->assertSame(0, $importer->sync());

        $state->refresh();
        $this->assertSame($watermark->timestamp, $state->watermark_at->timestamp);
        $this->assertSame(
            $now->addHour()->timestamp,
            $state->recovery_until_at->timestamp
        );
        $this->assertSame(1, $state->consecutive_failures);
    }

    public function test_recovery_imports_user_contributions_and_clears_the_alarm(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        $initial = CarbonImmutable::parse('2026-05-01 00:00:00', 'UTC');
        $from = CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC');
        $until = $now->subHour();
        $verifiedAt = CarbonImmutable::parse('2026-06-10 00:00:00', 'UTC');

        $link = MaddraxikonAccountLink::factory()->create([
            'wiki_user_id' => 55,
            'wiki_username' => 'Alter Name',
            'first_verified_at' => $verifiedAt->setTimezone(config('app.timezone')),
            'verified_at' => $verifiedAt->setTimezone(config('app.timezone')),
        ]);
        $this->makeEligible($link);
        $state = MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'initial_watermark_at' => $initial->setTimezone(config('app.timezone')),
            'watermark_at' => $from->setTimezone(config('app.timezone')),
            'recovery_required_at' => $until->setTimezone(config('app.timezone')),
            'recovery_from_at' => $from->setTimezone(config('app.timezone')),
            'recovery_until_at' => $until->setTimezone(config('app.timezone')),
            'consecutive_failures' => 1,
        ]);

        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('userContributions')
                ->once()
                ->withArgs(fn (
                    array $ids,
                    mixed $actualFrom,
                    mixed $actualUntil
                ): bool => $ids === [55]
                    && $actualFrom->equalTo($from)
                    && $actualUntil->equalTo($until))
                ->andReturn([
                    $this->userContribution(100, 55, '2026-06-05T10:00:00Z'),
                    $this->userContribution(101, 55, '2026-06-20T10:00:00Z'),
                    $this->userContribution(
                        102,
                        55,
                        '2026-06-21T10:00:00Z',
                        isNew: true,
                        pageId: 501
                    ),
                    $this->userContribution(103, 999, '2026-06-22T10:00:00Z'),
                    ['revid' => 104, 'userhidden' => true],
                ])
        );

        $this->assertSame(
            2,
            app(MaddraxikonContributionImporter::class)->recover($from, $until)
        );
        $this->assertDatabaseCount('maddraxikon_contributions', 2);

        $edit = MaddraxikonContribution::query()
            ->where('revision_id', 101)
            ->firstOrFail();
        $article = MaddraxikonContribution::query()
            ->where('revision_id', 102)
            ->firstOrFail();

        $this->assertNull($edit->rc_id);
        $this->assertSame(600, $edit->old_size);
        $this->assertSame(650, $edit->new_size);
        $this->assertSame(MaddraxikonContributionType::New, $article->type);
        $this->assertSame(MaddraxikonContributionStatus::Rejected, $edit->status);
        $this->assertSame(MaddraxikonContributionStatus::Rejected, $article->status);
        $this->assertSame(
            'recovery_bot_status_unverifiable',
            $edit->status_reason
        );
        $this->assertSame(
            'recovery_bot_status_unverifiable',
            $article->status_reason
        );
        $this->assertNotNull($edit->checked_at);
        $this->assertNotNull($article->checked_at);
        $this->assertDatabaseCount('maddraxikon_reward_events', 0);
        $this->assertDatabaseCount('user_points', 0);
        $this->assertSame('Neuer Name', $link->fresh()->wiki_username);

        $state->refresh();
        $this->assertSame($until->timestamp, $state->watermark_at->timestamp);
        $this->assertNull($state->recovery_required_at);
        $this->assertNull($state->recovery_from_at);
        $this->assertNull($state->recovery_until_at);
        $this->assertSame($from->timestamp, $state->last_recovered_from_at->timestamp);
        $this->assertSame($until->timestamp, $state->last_recovered_until_at->timestamp);
        $this->assertSame(2, $state->last_recovered_count);
        $this->assertSame(0, $state->consecutive_failures);
    }

    public function test_recovery_requires_an_open_alarm_and_never_calls_the_api_otherwise(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        $from = $now->subDays(2);
        $until = $now->subDay();
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'initial_watermark_at' => $now->subMonth(),
            'watermark_at' => $from,
        ]);
        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldNotReceive('userContributions')
        );

        try {
            app(MaddraxikonContributionImporter::class)
                ->recover($from, $until);
            $this->fail('Recovery ohne offenen Alarm muss abgelehnt werden.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString(
                'offenen Recovery-Alarm',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('maddraxikon_contributions', 0);
    }

    public function test_recovery_cannot_start_early_or_end_after_the_open_gap(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        $from = $now->subDays(3);
        $until = $now->subDay();
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'initial_watermark_at' => $now->subMonth(),
            'watermark_at' => $from,
            'recovery_required_at' => $now->subHour(),
            'recovery_from_at' => $from,
            'recovery_until_at' => $until,
        ]);
        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldNotReceive('userContributions')
        );
        $importer = app(MaddraxikonContributionImporter::class);

        foreach ([
            [$from->subSecond(), $until],
            [$from, $until->addSecond()],
        ] as [$invalidFrom, $invalidUntil]) {
            try {
                $importer->recover($invalidFrom, $invalidUntil);
                $this->fail('Das Recovery-Fenster muss in der offenen Lücke bleiben.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString(
                    'Recovery',
                    $exception->getMessage()
                );
            }
        }

        $this->assertDatabaseCount('maddraxikon_contributions', 0);
    }

    public function test_partial_recovery_advances_only_the_contiguous_window_start(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        $initial = CarbonImmutable::parse('2026-05-01 00:00:00', 'UTC');
        $from = CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC');
        $partialUntil = CarbonImmutable::parse('2026-06-20 00:00:00', 'UTC');
        $openUntil = CarbonImmutable::parse('2026-07-17 00:00:00', 'UTC');
        $state = MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'initial_watermark_at' => $initial->setTimezone(config('app.timezone')),
            'watermark_at' => $from->setTimezone(config('app.timezone')),
            'recovery_required_at' => $now->subHour()->setTimezone(config('app.timezone')),
            'recovery_from_at' => $from->setTimezone(config('app.timezone')),
            'recovery_until_at' => $openUntil->setTimezone(config('app.timezone')),
            'consecutive_failures' => 1,
        ]);

        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('userContributions')
                ->once()
                ->withArgs(fn (
                    array $ids,
                    mixed $actualFrom,
                    mixed $actualUntil
                ): bool => $ids === []
                    && $actualFrom->equalTo($from)
                    && $actualUntil->equalTo($partialUntil))
                ->andReturn([])
        );

        $this->assertSame(
            0,
            app(MaddraxikonContributionImporter::class)
                ->recover($from, $partialUntil)
        );

        $state->refresh();
        $this->assertSame(
            $partialUntil->timestamp,
            $state->watermark_at->timestamp
        );
        $this->assertNotNull($state->recovery_required_at);
        $this->assertSame(
            $partialUntil->timestamp,
            $state->recovery_from_at->timestamp
        );
        $this->assertSame(
            $openUntil->timestamp,
            $state->recovery_until_at->timestamp
        );
        $this->assertStringContainsString(
            'Watermark wird nicht übersprungen',
            $state->last_error
        );
    }

    public function test_recovery_api_failure_preserves_original_exception_and_records_error(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        $from = $now->subDays(10);
        $until = $now->subHour();
        $link = MaddraxikonAccountLink::factory()->create([
            'wiki_user_id' => 55,
            'verified_at' => $from->subDay(),
        ]);
        $this->makeEligible($link);
        $state = MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'initial_watermark_at' => $now->subMonth(),
            'watermark_at' => $from,
            'last_succeeded_at' => $from,
            'recovery_required_at' => $until,
            'recovery_from_at' => $from,
            'recovery_until_at' => $until,
            'consecutive_failures' => 1,
        ]);
        $this->mock(
            MaddraxikonApiClient::class,
            function (MockInterface $mock): void {
                $mock->shouldNotReceive('recentChanges');
                $mock->shouldReceive('userContributions')
                    ->once()
                    ->andReturnUsing(function (): never {
                        $this->assertSame(
                            0,
                            app(MaddraxikonContributionImporter::class)
                                ->sync(force: true)
                        );

                        throw new MaddraxikonApiException(
                            'Recovery-API nicht erreichbar'
                        );
                    });
            }
        );

        try {
            app(MaddraxikonContributionImporter::class)->recover(
                $from,
                $until
            );
            $this->fail('Der ursprüngliche API-Fehler muss weitergereicht werden.');
        } catch (MaddraxikonApiException $exception) {
            $this->assertSame(
                'Recovery-API nicht erreichbar',
                $exception->getMessage()
            );
        }

        $state->refresh();
        $this->assertSame(2, $state->consecutive_failures);
        $this->assertSame(
            'Recovery-API nicht erreichbar',
            $state->last_error
        );
        $this->assertNotNull($state->recovery_required_at);
        $this->assertSame($from->timestamp, $state->watermark_at->timestamp);
    }

    public function test_stale_recovery_failure_cannot_overwrite_a_closed_alarm(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        $from = $now->subDays(10);
        $until = $now->subHour();
        $link = MaddraxikonAccountLink::factory()->create([
            'wiki_user_id' => 55,
            'verified_at' => $from->subDay(),
        ]);
        $this->makeEligible($link);
        $state = MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'initial_watermark_at' => $now->subMonth(),
            'watermark_at' => $from,
            'last_succeeded_at' => $from,
            'recovery_required_at' => $until,
            'recovery_from_at' => $from,
            'recovery_until_at' => $until,
            'consecutive_failures' => 0,
        ]);
        $this->mock(
            MaddraxikonApiClient::class,
            function (MockInterface $mock) use ($state, $until): void {
                $mock->shouldReceive('userContributions')
                    ->once()
                    ->andReturnUsing(function () use ($state, $until): never {
                        $state->update([
                            'watermark_at' => $until,
                            'recovery_required_at' => null,
                            'recovery_from_at' => null,
                            'recovery_until_at' => null,
                            'last_error_at' => null,
                            'last_error' => null,
                            'consecutive_failures' => 0,
                        ]);

                        throw new MaddraxikonApiException(
                            'Verspäteter Fehler eines überholten Recovery-Laufs'
                        );
                    });
            }
        );

        try {
            app(MaddraxikonContributionImporter::class)->recover(
                $from,
                $until
            );
            $this->fail('Der API-Fehler muss weitergereicht werden.');
        } catch (MaddraxikonApiException) {
            // expected
        }

        $state->refresh();
        $this->assertNull($state->recovery_required_at);
        $this->assertSame(0, $state->consecutive_failures);
        $this->assertNull($state->last_error_at);
        $this->assertNull($state->last_error);
        $this->assertSame($until->timestamp, $state->watermark_at->timestamp);
    }

    public function test_missing_members_team_never_advances_normal_watermark(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        $watermark = $now->subHour();
        $state = MaddraxikonSyncState::factory()->create([
            'watermark_at' => $watermark,
            'last_succeeded_at' => $watermark,
        ]);
        Team::membersTeam()?->delete();
        Team::clearMembersTeamCache();
        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldNotReceive('recentChanges')
        );

        try {
            app(MaddraxikonContributionImporter::class)->sync();
            $this->fail('Ein fehlendes Mitglieder-Team muss den Sync abbrechen.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString(
                'Mitglieder-Team fehlt',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            $watermark->timestamp,
            $state->fresh()->watermark_at->timestamp
        );
    }

    public function test_missing_members_team_never_closes_recovery_window(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        $from = $now->subDays(10);
        $until = $now->subHour();
        $state = MaddraxikonSyncState::factory()->create([
            'initial_watermark_at' => $now->subMonth(),
            'watermark_at' => $from,
            'last_succeeded_at' => $from,
            'recovery_required_at' => $until,
            'recovery_from_at' => $from,
            'recovery_until_at' => $until,
        ]);
        Team::membersTeam()?->delete();
        Team::clearMembersTeamCache();
        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldNotReceive('userContributions')
        );

        try {
            app(MaddraxikonContributionImporter::class)->recover($from, $until);
            $this->fail('Ein fehlendes Mitglieder-Team muss Recovery abbrechen.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString(
                'Mitglieder-Team fehlt',
                $exception->getMessage()
            );
        }

        $state->refresh();
        $this->assertSame($from->timestamp, $state->watermark_at->timestamp);
        $this->assertNotNull($state->recovery_required_at);
        $this->assertSame($from->timestamp, $state->recovery_from_at->timestamp);
    }

    public function test_older_failure_cannot_overwrite_newer_run_state_in_same_second(): void
    {
        $now = CarbonImmutable::parse('2026-07-18T12:00:00Z');
        $this->travelTo($now);
        $watermark = $now->subHour();
        $state = MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => $watermark,
            'last_succeeded_at' => $watermark,
            'consecutive_failures' => 0,
        ]);

        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('recentChanges')
                ->once()
                ->andReturnUsing(function () use ($now, $state): never {
                    $state->refresh();
                    $state->update([
                        'run_sequence' => $state->run_sequence + 1,
                        'last_succeeded_at' => $now,
                        'last_error_at' => null,
                        'last_error' => null,
                        'consecutive_failures' => 0,
                    ]);

                    throw new MaddraxikonApiException(
                        'Verspaeteter Fehler des aelteren Laufs'
                    );
                })
        );

        try {
            app(MaddraxikonContributionImporter::class)->sync();
            $this->fail('Der API-Fehler muss weitergereicht werden.');
        } catch (MaddraxikonApiException) {
            // expected
        }

        $state->refresh();
        $this->assertSame(2, $state->run_sequence);
        $this->assertSame(0, $state->consecutive_failures);
        $this->assertNull($state->last_error_at);
        $this->assertNull($state->last_error);
    }

    public function test_api_failure_keeps_watermark_and_records_operational_error(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        $watermark = $now->subHour()->setTimezone(config('app.timezone'));

        $state = MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'watermark_at' => $watermark,
            'consecutive_failures' => 2,
        ]);

        $this->mock(
            MaddraxikonApiClient::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('recentChanges')
                ->once()
                ->andThrow(new MaddraxikonApiException('API nicht erreichbar'))
        );

        try {
            app(MaddraxikonContributionImporter::class)->sync();
            $this->fail('Expected MaddraxikonApiException was not thrown.');
        } catch (MaddraxikonApiException) {
            // expected
        }

        $state->refresh();

        $this->assertSame($watermark->timestamp, $state->watermark_at->timestamp);
        $this->assertSame(3, $state->consecutive_failures);
        $this->assertSame('API nicht erreichbar', $state->last_error);
        $this->assertNotNull($state->last_error_at);
    }

    private function makeEligible(MaddraxikonAccountLink $link): Team
    {
        $membersTeam = Team::query()
            ->where('name', 'Mitglieder')
            ->first();

        if (! $membersTeam) {
            $membersTeam = Team::factory()->create([
                'name' => 'Mitglieder',
                'personal_team' => false,
            ]);
            Team::clearMembersTeamCache();
        }

        $membersTeam->users()->syncWithoutDetaching([
            $link->user_id => ['role' => Role::Mitglied->value],
        ]);

        return $membersTeam;
    }

    /**
     * @return array<string, mixed>
     */
    private function userContribution(
        int $revisionId,
        int $wikiUserId,
        string $timestamp,
        bool $isNew = false,
        int $pageId = 500
    ): array {
        return [
            'userid' => $wikiUserId,
            'user' => 'Neuer Name',
            'pageid' => $pageId,
            'revid' => $revisionId,
            'parentid' => $isNew ? 0 : $revisionId - 1,
            'ns' => 0,
            'title' => $isNew ? 'Neuer Artikel' : 'Bestehender Artikel',
            'timestamp' => $timestamp,
            'size' => 650,
            'sizediff' => $isNew ? 650 : 50,
            'tags' => [],
            ...($isNew ? ['new' => true] : []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function change(
        int $rcId,
        int $revisionId,
        int $wikiUserId,
        string $wikiUsername,
        string $timestamp,
        string $type = 'edit',
        int $pageId = 500,
        int $namespaceId = 0,
        int $oldRevisionId = 100,
        int $oldSize = 600,
        int $newSize = 650,
    ): array {
        return [
            'type' => $type,
            'ns' => $namespaceId,
            'title' => $type === 'new' ? 'Neuer Artikel' : 'Bestehender Artikel',
            'pageid' => $pageId,
            'revid' => $revisionId,
            'old_revid' => $oldRevisionId,
            'rcid' => $rcId,
            'user' => $wikiUsername,
            'userid' => $wikiUserId,
            'oldlen' => $oldSize,
            'newlen' => $newSize,
            'timestamp' => $timestamp,
            'tags' => [],
        ];
    }
}
