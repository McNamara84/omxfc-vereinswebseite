<?php

declare(strict_types=1);

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
use Mockery\MockInterface;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonContributionImporterTestFixtures::class);
dataset('MaddraxikonContributionImporterTest.dstSessionCases', static fn (): array => ['spring forward' => ['2026-03-29T04:00:00Z', '2026-03-29T00:50:00Z', '2026-03-29T01:10:00Z'], 'fall back' => ['2026-10-25T04:00:00Z', '2026-10-25T00:50:00Z', '2026-10-25T01:10:00Z']]);
beforeEach(function (): void {
    Team::clearMembersTeamCache();
    if (! Team::query()->where('name', 'Mitglieder')->exists()) {
        Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
    }
    Team::clearMembersTeamCache();
    config(['maddraxikon.features.sync_enabled' => true, 'maddraxikon.wiki_key' => 'maddraxikon-de', 'maddraxikon.allowed_namespaces' => [0, 10, 14, 102, 106, 108, 112, 420], 'maddraxikon.session_window_minutes' => 30, 'maddraxikon.evaluation_delay_hours' => 24, 'maddraxikon.sync.overlap_minutes' => 10, 'maddraxikon.sync.recent_changes_retention_days' => 30, 'maddraxikon.sync.max_window_minutes' => 360, 'maddraxikon.sync.recovery_max_window_days' => 90]);
});
test('first run sets no backfill watermark without calling the api', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldNotReceive('recentChanges'));
    expect(app(MaddraxikonContributionImporter::class)->sync())->toBe(0);
    $this->assertDatabaseHas('maddraxikon_sync_states', ['wiki_key' => 'maddraxikon-de', 'watermark_at' => $now->format('Y-m-d H:i:s'), 'watermark_at_epoch' => $now->timestamp, 'initial_watermark_at' => $now->format('Y-m-d H:i:s'), 'initial_watermark_at_epoch' => $now->timestamp, 'last_succeeded_at' => $now->format('Y-m-d H:i:s'), 'last_succeeded_at_epoch' => $now->timestamp, 'last_imported_count' => 0]);
    $this->assertDatabaseCount('maddraxikon_contributions', 0);
});
test('backlog is advanced in bounded windows without appearing stale', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $watermark = $now->subHours(12);
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'watermark_at' => $watermark, 'last_succeeded_at' => $watermark]);
    $expectedFrom = $watermark->subMinutes(10);
    $expectedUntil = $watermark->addHours(6);
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldReceive('recentChanges')->once()->withArgs(fn (mixed $actualFrom, mixed $actualUntil): bool => $actualFrom->equalTo($expectedFrom) && $actualUntil->equalTo($expectedUntil))->andReturn([]));
    expect(app(MaddraxikonContributionImporter::class)->sync())->toBe(0);
    $state = MaddraxikonSyncState::query()->where('wiki_key', 'maddraxikon-de')->firstOrFail();
    expect($state->watermark_at->timestamp)->toBe($expectedUntil->timestamp)
        ->and($state->last_succeeded_at->timestamp)->toBe($now->timestamp);
});
test('sync imports only linked eligible changes and builds edit sessions', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $link = MaddraxikonAccountLink::factory()->create(['wiki_user_id' => 55, 'wiki_username' => 'Alter Name', 'verified_at' => $now->subDays(2)->setTimezone(config('app.timezone'))]);
    $this->makeEligible($link);
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'watermark_at' => $now->subHours(3)->setTimezone(config('app.timezone'))]);
    $changes = [
        $this->change(1, 101, 55, 'Neuer Name', '2026-07-18T08:00:00Z'),
        $this->change(2, 102, 55, 'Neuer Name', '2026-07-18T08:30:00Z'),
        $this->change(3, 103, 55, 'Neuer Name', '2026-07-18T09:00:01Z'),
        $this->change(4, 104, 55, 'Neuer Name', '2026-07-18T09:10:00Z', type: 'new', pageId: 999, oldRevisionId: 0, oldSize: 0, newSize: 700),
        // Not linked.
        $this->change(5, 105, 999, 'Fremd', '2026-07-18T09:20:00Z'),
        // Explicitly excluded namespace.
        $this->change(6, 106, 55, 'Neuer Name', '2026-07-18T09:30:00Z', namespaceId: 1),
        // Defense in depth despite rcshow.
        [...$this->change(7, 107, 55, 'Neuer Name', '2026-07-18T09:40:00Z'), 'bot' => true],
    ];
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldReceive('recentChanges')->once()->andReturn($changes));
    expect(app(MaddraxikonContributionImporter::class)->sync())->toBe(4);
    $this->assertDatabaseCount('maddraxikon_contributions', 4);
    $first = MaddraxikonContribution::query()->where('revision_id', 101)->firstOrFail();
    $second = MaddraxikonContribution::query()->where('revision_id', 102)->firstOrFail();
    $third = MaddraxikonContribution::query()->where('revision_id', 103)->firstOrFail();
    $newArticle = MaddraxikonContribution::query()->where('revision_id', 104)->firstOrFail();
    expect($first->session_anchor_revision_id)->toBe(101)
        ->and($second->session_anchor_revision_id)->toBe(101)
        ->and($third->session_anchor_revision_id)->toBe(103)
        ->and($first->eligible_after->utc()->format('Y-m-d H:i:s'))->toBe('2026-07-19 08:30:00')
        ->and($second->eligible_after->timestamp)->toBe($first->eligible_after->timestamp)
        ->and($newArticle->session_anchor_revision_id)->toBeNull()
        ->and($newArticle->type)->toBe(MaddraxikonContributionType::New)
        ->and($newArticle->status)->toBe(MaddraxikonContributionStatus::Pending)
        ->and($link->fresh()->wiki_username)->toBe('Neuer Name');
    $state = MaddraxikonSyncState::query()->where('wiki_key', 'maddraxikon-de')->firstOrFail();
    expect($state->watermark_at->timestamp)->toBe($now->timestamp)
        ->and($state->last_imported_count)->toBe(4)
        ->and($state->last_seen_rc_id)->toBe(7);
});
test('hidden and unattributable changes do not block the batch', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $link = MaddraxikonAccountLink::factory()->create(['wiki_user_id' => 55, 'wiki_username' => 'Verified Name', 'verified_at' => $now->subDay()->setTimezone(config('app.timezone'))]);
    $this->makeEligible($link);
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'watermark_at' => $now->subHours(3)->setTimezone(config('app.timezone'))]);
    $hidden = $this->change(10, 110, 55, 'Hidden', '2026-07-18T10:00:00Z');
    unset($hidden['user'], $hidden['userid']);
    $hidden['userhidden'] = true;
    $legacyUsernameOnly = $this->change(11, 111, 55, 'Verified Name', '2026-07-18T10:05:00Z');
    unset($legacyUsernameOnly['userid']);
    $missingUsername = $this->change(12, 112, 55, 'Missing', '2026-07-18T10:10:00Z');
    unset($missingUsername['user']);
    $visible = $this->change(13, 113, 55, 'Verified Name', '2026-07-18T10:15:00Z');
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldReceive('recentChanges')->once()->andReturn([$hidden, $legacyUsernameOnly, $missingUsername, $visible]));
    expect(app(MaddraxikonContributionImporter::class)->sync())->toBe(1);
    $this->assertDatabaseHas('maddraxikon_contributions', ['revision_id' => 113, 'wiki_user_id' => 55]);
    $this->assertDatabaseMissing('maddraxikon_contributions', ['revision_id' => 110]);
    $this->assertDatabaseMissing('maddraxikon_contributions', ['revision_id' => 111]);
    $this->assertDatabaseMissing('maddraxikon_contributions', ['revision_id' => 112]);
    expect(MaddraxikonSyncState::query()->firstOrFail()->last_seen_rc_id)->toBe(13);
});
test('active links of non members and applicants are not imported', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $membersTeam = Team::membersTeam();
    expect($membersTeam)->not->toBeNull();
    MaddraxikonAccountLink::factory()->create(['wiki_user_id' => 55, 'verified_at' => $now->subDay()->setTimezone(config('app.timezone'))]);
    $applicantLink = MaddraxikonAccountLink::factory()->create(['wiki_user_id' => 56, 'verified_at' => $now->subDay()->setTimezone(config('app.timezone'))]);
    $membersTeam->users()->attach($applicantLink->user_id, ['role' => Role::Anwaerter->value]);
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'watermark_at' => $now->subHours(3)->setTimezone(config('app.timezone'))]);
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldReceive('recentChanges')->once()->andReturn([$this->change(1, 101, 55, 'Nonmember', '2026-07-18T10:00:00Z'), $this->change(2, 102, 56, 'Applicant', '2026-07-18T10:05:00Z')]));
    expect(app(MaddraxikonContributionImporter::class)->sync())->toBe(0);
    $this->assertDatabaseCount('maddraxikon_contributions', 0);
});
test('every configured namespace is imported', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $link = MaddraxikonAccountLink::factory()->create(['wiki_user_id' => 55, 'verified_at' => $now->subDay()->setTimezone(config('app.timezone'))]);
    $this->makeEligible($link);
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'watermark_at' => $now->subHours(3)->setTimezone(config('app.timezone'))]);
    $allowedNamespaces = config('maddraxikon.allowed_namespaces');
    $changes = [];
    foreach ($allowedNamespaces as $index => $namespaceId) {
        $changes[] = $this->change($index + 1, $index + 101, 55, 'Wiki', '2026-07-18T10:00:00Z', pageId: $index + 500, namespaceId: $namespaceId);
    }
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldReceive('recentChanges')->once()->andReturn($changes));
    expect(app(MaddraxikonContributionImporter::class)->sync())->toBe(count($allowedNamespaces))
        ->and(MaddraxikonContribution::query()->orderBy('namespace_id')->pluck('namespace_id')->all())->toBe($allowedNamespaces);
});
test('session rebuild does not cross a later verification boundary', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $oldOccurredAt = $now->subHours(2);
    $verifiedAt = $oldOccurredAt->addMinutes(10);
    $link = MaddraxikonAccountLink::factory()->create(['wiki_user_id' => 55, 'wiki_username' => 'Wiki', 'first_verified_at' => $now->subMonth()->setTimezone(config('app.timezone')), 'verified_at' => $verifiedAt->setTimezone(config('app.timezone'))]);
    $this->makeEligible($link);
    $oldEligibleAfter = $oldOccurredAt->addDay();
    $oldContribution = MaddraxikonContribution::factory()->create(['wiki_key' => 'maddraxikon-de', 'rc_id' => 90, 'revision_id' => 90, 'parent_revision_id' => 89, 'page_id' => 500, 'wiki_user_id' => 55, 'wiki_username' => 'Wiki', 'account_link_id' => $link->id, 'user_id' => $link->user_id, 'type' => MaddraxikonContributionType::Edit, 'occurred_at' => $oldOccurredAt->setTimezone(config('app.timezone')), 'session_anchor_revision_id' => 90, 'eligible_after' => $oldEligibleAfter->setTimezone(config('app.timezone'))]);
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'watermark_at' => $now->subHours(3)->setTimezone(config('app.timezone'))]);
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldReceive('recentChanges')->once()->andReturn([$this->change(101, 101, 55, 'Wiki', '2026-07-18T10:20:00Z')]));
    expect(app(MaddraxikonContributionImporter::class)->sync())->toBe(1);
    $newContribution = MaddraxikonContribution::query()->where('revision_id', 101)->firstOrFail();
    expect($oldContribution->fresh()->session_anchor_revision_id)->toBe(90)
        ->and($oldContribution->fresh()->eligible_after->timestamp)->toBe($oldEligibleAfter->timestamp)
        ->and($newContribution->session_anchor_revision_id)->toBe(101);
});
test('changes before the current verification period are not imported', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $link = MaddraxikonAccountLink::factory()->create(['wiki_user_id' => 55, 'verified_at' => $now->subHour()->setTimezone(config('app.timezone'))]);
    $this->makeEligible($link);
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'watermark_at' => $now->subHours(3)->setTimezone(config('app.timezone'))]);
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldReceive('recentChanges')->once()->andReturn([$this->change(1, 101, 55, 'Wiki', '2026-07-18T10:00:00Z')]));
    expect(app(MaddraxikonContributionImporter::class)->sync())->toBe(0);
    $this->assertDatabaseCount('maddraxikon_contributions', 0);
});
test('overlap replay is idempotent', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $link = MaddraxikonAccountLink::factory()->create(['wiki_user_id' => 55, 'verified_at' => $now->subDay()->setTimezone(config('app.timezone'))]);
    $this->makeEligible($link);
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'watermark_at' => $now->subHour()->setTimezone(config('app.timezone'))]);
    $change = $this->change(1, 101, 55, 'Wiki', '2026-07-18T11:30:00Z');
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldReceive('recentChanges')->twice()->andReturn([$change]));
    $importer = app(MaddraxikonContributionImporter::class);
    expect($importer->sync())->toBe(1);
    $this->travelTo($now->addMinutes(15));
    expect($importer->sync())->toBe(0);
    $this->assertDatabaseCount('maddraxikon_contributions', 1);
});
test('slower overlapping sync cannot regress the watermark', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $newerWatermark = $now->addMinute();
    $state = MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'watermark_at' => $now->subHour(), 'last_imported_count' => 9]);
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldReceive('recentChanges')->once()->andReturnUsing(function () use ($newerWatermark, $state): array {
        $state->update(['watermark_at' => $newerWatermark, 'last_succeeded_at' => $newerWatermark, 'last_imported_count' => 9]);

        return [];
    }));
    expect(app(MaddraxikonContributionImporter::class)->sync())->toBe(0);
    $state->refresh();
    expect($state->watermark_at->timestamp)->toBe($newerWatermark->timestamp)
        ->and($state->last_imported_count)->toBe(9);
});
test('edit sessions use unambiguous utc instants across dst', function (string $nowIso, string $firstIso, string $secondIso): void {
    $now = CarbonImmutable::parse($nowIso);
    $firstAt = CarbonImmutable::parse($firstIso);
    $secondAt = CarbonImmutable::parse($secondIso);
    $this->travelTo($now);
    $link = MaddraxikonAccountLink::factory()->create(['wiki_user_id' => 55, 'verified_at' => $now->subDay()]);
    $this->makeEligible($link);
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'watermark_at' => $now->subHours(3)]);
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldReceive('recentChanges')->once()->andReturn([$this->change(1, 101, 55, 'Wiki', $firstIso), $this->change(2, 102, 55, 'Wiki', $secondIso)]));
    expect(app(MaddraxikonContributionImporter::class)->sync())->toBe(2);
    $first = MaddraxikonContribution::query()->where('revision_id', 101)->sole();
    $second = MaddraxikonContribution::query()->where('revision_id', 102)->sole();
    expect($first->occurred_at_epoch)->toBe($firstAt->timestamp)
        ->and($second->occurred_at_epoch)->toBe($secondAt->timestamp)
        ->and($first->session_anchor_revision_id)->toBe(101)
        ->and($second->session_anchor_revision_id)->toBe(101)
        ->and($first->eligible_after_epoch)->toBe($secondAt->addHours(24)->timestamp)
        ->and($second->eligible_after_epoch)->toBe($first->eligible_after_epoch);
})->with('MaddraxikonContributionImporterTest.dstSessionCases');
test('expired recent changes window opens and extends recovery alarm', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $watermark = $now->subDays(31);
    $state = MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'initial_watermark_at' => $now->subDays(40)->setTimezone(config('app.timezone')), 'watermark_at' => $watermark->setTimezone(config('app.timezone')), 'consecutive_failures' => 0]);
    $this->mock(MaddraxikonApiClient::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('recentChanges');
        $mock->shouldNotReceive('userContributions');
    });
    $importer = app(MaddraxikonContributionImporter::class);
    expect($importer->sync())->toBe(0);
    $state->refresh();
    expect($state->watermark_at->timestamp)->toBe($watermark->timestamp)
        ->and($state->recovery_from_at->timestamp)->toBe($watermark->timestamp)
        ->and($state->recovery_until_at->timestamp)->toBe($now->timestamp)
        ->and($state->consecutive_failures)->toBe(1)
        ->and($state->last_error)->toContain('Watermark wird nicht übersprungen');
    $this->travelTo($now->addHour());
    expect($importer->sync())->toBe(0);
    $state->refresh();
    expect($state->watermark_at->timestamp)->toBe($watermark->timestamp)
        ->and($state->recovery_until_at->timestamp)->toBe($now->addHour()->timestamp)
        ->and($state->consecutive_failures)->toBe(1);
});
test('recovery imports user contributions and clears the alarm', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $initial = CarbonImmutable::parse('2026-05-01 00:00:00', 'UTC');
    $from = CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC');
    $until = $now->subHour();
    $verifiedAt = CarbonImmutable::parse('2026-06-10 00:00:00', 'UTC');
    $link = MaddraxikonAccountLink::factory()->create(['wiki_user_id' => 55, 'wiki_username' => 'Alter Name', 'first_verified_at' => $verifiedAt->setTimezone(config('app.timezone')), 'verified_at' => $verifiedAt->setTimezone(config('app.timezone'))]);
    $this->makeEligible($link);
    $state = MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'initial_watermark_at' => $initial->setTimezone(config('app.timezone')), 'watermark_at' => $from->setTimezone(config('app.timezone')), 'recovery_required_at' => $until->setTimezone(config('app.timezone')), 'recovery_from_at' => $from->setTimezone(config('app.timezone')), 'recovery_until_at' => $until->setTimezone(config('app.timezone')), 'consecutive_failures' => 1]);
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldReceive('userContributions')->once()->withArgs(fn (array $ids, mixed $actualFrom, mixed $actualUntil): bool => $ids === [55] && $actualFrom->equalTo($from) && $actualUntil->equalTo($until))->andReturn([$this->userContribution(100, 55, '2026-06-05T10:00:00Z'), $this->userContribution(101, 55, '2026-06-20T10:00:00Z'), $this->userContribution(102, 55, '2026-06-21T10:00:00Z', isNew: true, pageId: 501), $this->userContribution(103, 999, '2026-06-22T10:00:00Z'), ['revid' => 104, 'userhidden' => true]]));
    expect(app(MaddraxikonContributionImporter::class)->recover($from, $until))->toBe(2);
    $this->assertDatabaseCount('maddraxikon_contributions', 2);
    $edit = MaddraxikonContribution::query()->where('revision_id', 101)->firstOrFail();
    $article = MaddraxikonContribution::query()->where('revision_id', 102)->firstOrFail();
    expect($edit->rc_id)->toBeNull()
        ->and($edit->type)->toBe(MaddraxikonContributionType::Edit)
        ->and($edit->minor)->toBeFalse()
        ->and($edit->bot)->toBeFalse()
        ->and($edit->redirect)->toBeFalse()
        ->and($edit->old_size)->toBe(600)
        ->and($edit->new_size)->toBe(650)
        ->and($article->type)->toBe(MaddraxikonContributionType::New)
        ->and($edit->status)->toBe(MaddraxikonContributionStatus::Rejected)
        ->and($article->status)->toBe(MaddraxikonContributionStatus::Rejected)
        ->and($edit->status_reason)->toBe('recovery_bot_status_unverifiable')
        ->and($article->status_reason)->toBe('recovery_bot_status_unverifiable')
        ->and($edit->checked_at)->not->toBeNull()
        ->and($article->checked_at)->not->toBeNull();
    $this->assertDatabaseCount('maddraxikon_reward_events', 0);
    $this->assertDatabaseCount('user_points', 0);
    expect($link->fresh()->wiki_username)->toBe('Neuer Name');
    $state->refresh();
    expect($state->watermark_at->timestamp)->toBe($until->timestamp)
        ->and($state->recovery_required_at)->toBeNull()
        ->and($state->recovery_from_at)->toBeNull()
        ->and($state->recovery_until_at)->toBeNull()
        ->and($state->last_recovered_from_at->timestamp)->toBe($from->timestamp)
        ->and($state->last_recovered_until_at->timestamp)->toBe($until->timestamp)
        ->and($state->last_recovered_count)->toBe(2)
        ->and($state->consecutive_failures)->toBe(0);
});
test('recovery requires an open alarm and never calls the api otherwise', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $from = $now->subDays(2);
    $until = $now->subDay();
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'initial_watermark_at' => $now->subMonth(), 'watermark_at' => $from]);
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldNotReceive('userContributions'));
    try {
        app(MaddraxikonContributionImporter::class)->recover($from, $until);
        $this->fail('Recovery ohne offenen Alarm muss abgelehnt werden.');
    } catch (\RuntimeException $exception) {
        expect($exception->getMessage())->toContain('offenen Recovery-Alarm');
    }
    $this->assertDatabaseCount('maddraxikon_contributions', 0);
});
test('recovery cannot start early or end after the open gap', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $from = $now->subDays(3);
    $until = $now->subDay();
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'initial_watermark_at' => $now->subMonth(), 'watermark_at' => $from, 'recovery_required_at' => $now->subHour(), 'recovery_from_at' => $from, 'recovery_until_at' => $until]);
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldNotReceive('userContributions'));
    $importer = app(MaddraxikonContributionImporter::class);
    foreach ([[$from->subSecond(), $until], [$from, $until->addSecond()]] as [$invalidFrom, $invalidUntil]) {
        try {
            $importer->recover($invalidFrom, $invalidUntil);
            $this->fail('Das Recovery-Fenster muss in der offenen Lücke bleiben.');
        } catch (\RuntimeException $exception) {
            expect($exception->getMessage())->toContain('Recovery');
        }
    }
    $this->assertDatabaseCount('maddraxikon_contributions', 0);
});
test('partial recovery advances only the contiguous window start', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $initial = CarbonImmutable::parse('2026-05-01 00:00:00', 'UTC');
    $from = CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC');
    $partialUntil = CarbonImmutable::parse('2026-06-20 00:00:00', 'UTC');
    $openUntil = CarbonImmutable::parse('2026-07-17 00:00:00', 'UTC');
    $state = MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'initial_watermark_at' => $initial->setTimezone(config('app.timezone')), 'watermark_at' => $from->setTimezone(config('app.timezone')), 'recovery_required_at' => $now->subHour()->setTimezone(config('app.timezone')), 'recovery_from_at' => $from->setTimezone(config('app.timezone')), 'recovery_until_at' => $openUntil->setTimezone(config('app.timezone')), 'consecutive_failures' => 1]);
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldReceive('userContributions')->once()->withArgs(fn (array $ids, mixed $actualFrom, mixed $actualUntil): bool => $ids === [] && $actualFrom->equalTo($from) && $actualUntil->equalTo($partialUntil))->andReturn([]));
    expect(app(MaddraxikonContributionImporter::class)->recover($from, $partialUntil))->toBe(0);
    $state->refresh();
    expect($state->watermark_at->timestamp)->toBe($partialUntil->timestamp)
        ->and($state->recovery_required_at)->not->toBeNull()
        ->and($state->recovery_from_at->timestamp)->toBe($partialUntil->timestamp)
        ->and($state->recovery_until_at->timestamp)->toBe($openUntil->timestamp)
        ->and($state->last_error)->toContain('Watermark wird nicht übersprungen');
});
test('recovery api failure preserves original exception and records error', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $from = $now->subDays(10);
    $until = $now->subHour();
    $link = MaddraxikonAccountLink::factory()->create(['wiki_user_id' => 55, 'verified_at' => $from->subDay()]);
    $this->makeEligible($link);
    $state = MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'initial_watermark_at' => $now->subMonth(), 'watermark_at' => $from, 'last_succeeded_at' => $from, 'recovery_required_at' => $until, 'recovery_from_at' => $from, 'recovery_until_at' => $until, 'consecutive_failures' => 1]);
    $this->mock(MaddraxikonApiClient::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('recentChanges');
        $mock->shouldReceive('userContributions')->once()->andReturnUsing(function (): never {
            expect(app(MaddraxikonContributionImporter::class)->sync(force: true))->toBe(0);
            throw new MaddraxikonApiException('Recovery-API nicht erreichbar');
        });
    });
    try {
        app(MaddraxikonContributionImporter::class)->recover($from, $until);
        $this->fail('Der ursprüngliche API-Fehler muss weitergereicht werden.');
    } catch (MaddraxikonApiException $exception) {
        expect($exception->getMessage())->toBe('Recovery-API nicht erreichbar');
    }
    $state->refresh();
    expect($state->consecutive_failures)->toBe(2)
        ->and($state->last_error)->toBe('Recovery-API nicht erreichbar')
        ->and($state->recovery_required_at)->not->toBeNull()
        ->and($state->watermark_at->timestamp)->toBe($from->timestamp);
});
test('stale recovery failure cannot overwrite a closed alarm', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $from = $now->subDays(10);
    $until = $now->subHour();
    $link = MaddraxikonAccountLink::factory()->create(['wiki_user_id' => 55, 'verified_at' => $from->subDay()]);
    $this->makeEligible($link);
    $state = MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'initial_watermark_at' => $now->subMonth(), 'watermark_at' => $from, 'last_succeeded_at' => $from, 'recovery_required_at' => $until, 'recovery_from_at' => $from, 'recovery_until_at' => $until, 'consecutive_failures' => 0]);
    $this->mock(MaddraxikonApiClient::class, function (MockInterface $mock) use ($state, $until): void {
        $mock->shouldReceive('userContributions')->once()->andReturnUsing(function () use ($state, $until): never {
            $state->update(['watermark_at' => $until, 'recovery_required_at' => null, 'recovery_from_at' => null, 'recovery_until_at' => null, 'last_error_at' => null, 'last_error' => null, 'consecutive_failures' => 0]);
            throw new MaddraxikonApiException('Verspäteter Fehler eines überholten Recovery-Laufs');
        });
    });
    try {
        app(MaddraxikonContributionImporter::class)->recover($from, $until);
        $this->fail('Der API-Fehler muss weitergereicht werden.');
    } catch (MaddraxikonApiException) {
        // expected
    }
    $state->refresh();
    expect($state->recovery_required_at)->toBeNull()
        ->and($state->consecutive_failures)->toBe(0)
        ->and($state->last_error_at)->toBeNull()
        ->and($state->last_error)->toBeNull()
        ->and($state->watermark_at->timestamp)->toBe($until->timestamp);
});
test('missing members team never advances normal watermark', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $watermark = $now->subHour();
    $state = MaddraxikonSyncState::factory()->create(['watermark_at' => $watermark, 'last_succeeded_at' => $watermark]);
    Team::membersTeam()?->delete();
    Team::clearMembersTeamCache();
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldNotReceive('recentChanges'));
    try {
        app(MaddraxikonContributionImporter::class)->sync();
        $this->fail('Ein fehlendes Mitglieder-Team muss den Sync abbrechen.');
    } catch (\LogicException $exception) {
        expect($exception->getMessage())->toContain('Mitglieder-Team fehlt');
    }
    expect($state->fresh()->watermark_at->timestamp)->toBe($watermark->timestamp);
});
test('missing members team never closes recovery window', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $from = $now->subDays(10);
    $until = $now->subHour();
    $state = MaddraxikonSyncState::factory()->create(['initial_watermark_at' => $now->subMonth(), 'watermark_at' => $from, 'last_succeeded_at' => $from, 'recovery_required_at' => $until, 'recovery_from_at' => $from, 'recovery_until_at' => $until]);
    Team::membersTeam()?->delete();
    Team::clearMembersTeamCache();
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldNotReceive('userContributions'));
    try {
        app(MaddraxikonContributionImporter::class)->recover($from, $until);
        $this->fail('Ein fehlendes Mitglieder-Team muss Recovery abbrechen.');
    } catch (\LogicException $exception) {
        expect($exception->getMessage())->toContain('Mitglieder-Team fehlt');
    }
    $state->refresh();
    expect($state->watermark_at->timestamp)->toBe($from->timestamp)
        ->and($state->recovery_required_at)->not->toBeNull()
        ->and($state->recovery_from_at->timestamp)->toBe($from->timestamp);
});
test('older failure cannot overwrite newer run state in same second', function (): void {
    $now = CarbonImmutable::parse('2026-07-18T12:00:00Z');
    $this->travelTo($now);
    $watermark = $now->subHour();
    $state = MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'watermark_at' => $watermark, 'last_succeeded_at' => $watermark, 'consecutive_failures' => 0]);
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldReceive('recentChanges')->once()->andReturnUsing(function () use ($now, $state): never {
        $state->refresh();
        $state->update(['run_sequence' => $state->run_sequence + 1, 'last_succeeded_at' => $now, 'last_error_at' => null, 'last_error' => null, 'consecutive_failures' => 0]);
        throw new MaddraxikonApiException('Verspaeteter Fehler des aelteren Laufs');
    }));
    try {
        app(MaddraxikonContributionImporter::class)->sync();
        $this->fail('Der API-Fehler muss weitergereicht werden.');
    } catch (MaddraxikonApiException) {
        // expected
    }
    $state->refresh();
    expect($state->run_sequence)->toBe(2)
        ->and($state->consecutive_failures)->toBe(0)
        ->and($state->last_error_at)->toBeNull()
        ->and($state->last_error)->toBeNull();
});
test('api failure keeps watermark and records operational error', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $watermark = $now->subHour()->setTimezone(config('app.timezone'));
    $state = MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'watermark_at' => $watermark, 'consecutive_failures' => 2]);
    $this->mock(MaddraxikonApiClient::class, fn (MockInterface $mock) => $mock->shouldReceive('recentChanges')->once()->andThrow(new MaddraxikonApiException('API nicht erreichbar')));
    try {
        app(MaddraxikonContributionImporter::class)->sync();
        $this->fail('Expected MaddraxikonApiException was not thrown.');
    } catch (MaddraxikonApiException) {
        // expected
    }
    $state->refresh();
    expect($state->watermark_at->timestamp)->toBe($watermark->timestamp)
        ->and($state->consecutive_failures)->toBe(3)
        ->and($state->last_error)->toBe('API nicht erreichbar')
        ->and($state->last_error_at)->not->toBeNull();
});
