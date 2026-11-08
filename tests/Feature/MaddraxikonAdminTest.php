<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonRewardEventStatus;
use App\Enums\Role;
use App\Jobs\EvaluateMaddraxikonContributions;
use App\Jobs\SyncMaddraxikonContributions;
use App\Livewire\MaddraxikonAdmin;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardEvent;
use App\Models\MaddraxikonRewardPolicy;
use App\Models\MaddraxikonRewardPolicyTier;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use App\Models\UserPoint;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use App\Services\Maddraxikon\MaddraxikonNamespaceHealthService;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonAdminTestFixtures::class);

beforeEach(function (): void {
    Config::set('app.testing_minimal_layout', true);
});

test('route is available only to admins', function (): void {
    $url = route('rewards.admin.maddraxikon', absolute: false);
    $route = Route::getRoutes()->getByName('rewards.admin.maddraxikon');
    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('belohnungen/admin/maddraxikon')
        ->and($route->gatherMiddleware())->toContain('maddraxikon.admin');
    $this->get($url)->assertRedirect('/login');
    $member = $this->actingMember();
    $this->actingAs($member)->get($url)->assertForbidden();
    $admin = $this->actingAdmin();
    $this->actingAs($admin)->get($url)->assertOk()->assertSee('Maddraxikon-Baxx');
});

test('admin role in an unrelated team grants no access', function (): void {
    $url = route('rewards.admin.maddraxikon', absolute: false);
    $user = $this->createUserWithRole(Role::Mitglied);
    $unrelatedTeam = Team::factory()->create(['name' => 'Unabhängiges Team', 'personal_team' => false]);
    $unrelatedTeam->users()->attach($user, ['role' => Role::Admin->value]);
    $user->forceFill(['current_team_id' => $unrelatedTeam->id])->save();
    $this->actingAs($user->fresh())->get($url)->assertForbidden();
});

test('members team admin keeps access after switching teams', function (): void {
    $url = route('rewards.admin.maddraxikon', absolute: false);
    $admin = $this->createUserWithRole(Role::Admin);
    $workingGroup = Team::factory()->create(['name' => 'Arbeitsgruppe Test', 'personal_team' => false]);
    $workingGroup->users()->attach($admin, ['role' => Role::Mitglied->value]);
    $admin->forceFill(['current_team_id' => $workingGroup->id])->save();
    $this->actingAs($admin->fresh())->get($url)->assertOk()->assertSee('Maddraxikon-Baxx');
    Config::set('maddraxikon.features.sync_enabled', true);
    Queue::fake();
    Livewire::actingAs($admin->fresh())->test(MaddraxikonAdmin::class)->call('dispatchSync')->assertHasNoErrors();
    Queue::assertPushed(SyncMaddraxikonContributions::class);
});

test('role revocation blocks subsequent livewire actions', function (): void {
    Config::set('maddraxikon.features.sync_enabled', true);
    Queue::fake();
    $admin = $this->actingAdmin();
    $component = Livewire::test(MaddraxikonAdmin::class);
    $admin->currentTeam->users()->updateExistingPivot($admin->id, ['role' => Role::Mitglied->value]);
    $component->call('dispatchSync')->assertForbidden();
    Queue::assertNotPushed(SyncMaddraxikonContributions::class);
});

test('dashboard reads local status without namespace request', function (): void {
    $this->actingAdmin();
    Config::set('maddraxikon.features.linking_enabled', true);
    Config::set('maddraxikon.features.sync_enabled', false);
    Config::set('maddraxikon.features.awards_enabled', true);
    MaddraxikonSyncState::factory()->create(['watermark_at' => now()->subMinute(), 'last_succeeded_at' => now()->subMinutes(2), 'last_imported_count' => 12, 'consecutive_failures' => 2, 'last_error' => 'Testfehler beim Import']);
    $member = $this->createUserWithRole(Role::Mitglied);
    $member->forceFill(['name' => 'Lokales Testmitglied'])->save();
    $link = MaddraxikonAccountLink::factory()->create(['user_id' => $member->id, 'wiki_username' => 'WikiTester']);
    foreach (MaddraxikonContributionStatus::cases() as $status) {
        MaddraxikonContribution::factory()->create(['account_link_id' => $link->id, 'user_id' => $member->id, 'wiki_user_id' => $link->wiki_user_id, 'wiki_username' => $link->wiki_username, 'status' => $status]);
    }
    $article = MaddraxikonContribution::factory()->newArticle()->create(['account_link_id' => $link->id, 'user_id' => $member->id, 'wiki_user_id' => $link->wiki_user_id, 'wiki_username' => $link->wiki_username, 'status' => MaddraxikonContributionStatus::Awarded]);
    MaddraxikonRewardEvent::factory()->create(['source_contribution_id' => $article->id, 'user_id' => $member->id, 'account_link_id' => $link->id, 'source_revision_id' => $article->revision_id, 'action_key' => MaddraxikonRewardEvent::ACTION_NEW_ARTICLE, 'status' => MaddraxikonRewardEventStatus::Awarded, 'awarded_points' => 5, 'awarded_at' => now()]);
    $healthService = Mockery::mock(MaddraxikonNamespaceHealthService::class);
    $healthService->shouldNotReceive('check');
    $this->app->instance(MaddraxikonNamespaceHealthService::class, $healthService);
    Livewire::test(MaddraxikonAdmin::class)->assertSet('namespaceHealth', null)->assertSee('OAuth-Verknüpfung')->assertSee('Aktiviert')->assertSee('Deaktiviert')->assertSee('Testfehler beim Import')->assertSee('Lokales Testmitglied')->assertSee('WikiTester')->assertSee('Neuer Artikel')->assertSee('Ausstehend')->assertSee('Qualifiziert')->assertSee('Abgelehnt')->assertSee('Gutgeschrieben');
});

test('namespace check runs only on explicit action', function (): void {
    $this->actingAdmin();
    $result = ['healthy' => false, 'expected' => [0 => '', 10 => 'Vorlage'], 'actual' => [0 => '', 10 => 'Template'], 'missing' => [], 'mismatched' => [10 => ['expected' => 'Vorlage', 'actual' => 'Template']]];
    $healthService = Mockery::mock(MaddraxikonNamespaceHealthService::class);
    $healthService->shouldReceive('check')->once()->andReturn($result);
    $this->app->instance(MaddraxikonNamespaceHealthService::class, $healthService);
    Livewire::test(MaddraxikonAdmin::class)->assertSet('namespaceHealth', null)->call('checkNamespaces')->assertSet('namespaceHealth.healthy', false)->assertSee('Namensraum-Abweichungen')->assertSee('Vorlage')->assertSee('Template')->assertDispatched('toast', type: 'warning', title: 'Namensraum-Abweichungen gefunden');
});

test('namespace check reports remote failure without breaking dashboard', function (): void {
    $this->actingAdmin();
    $healthService = Mockery::mock(MaddraxikonNamespaceHealthService::class);
    $healthService->shouldReceive('check')->once()->andThrow(new RuntimeException('Wiki nicht erreichbar'));
    $this->app->instance(MaddraxikonNamespaceHealthService::class, $healthService);
    Livewire::test(MaddraxikonAdmin::class)->call('checkNamespaces')->assertSet('namespaceHealth', null)->assertSet('namespaceHealthError', 'Die Namensräume konnten nicht geprüft werden. Details stehen im Anwendungsprotokoll.')->assertSee('Die Namensräume konnten nicht geprüft werden.')->assertDispatched('toast', type: 'error', title: 'Namensraum-Prüfung fehlgeschlagen');
});

test('manual actions dispatch enabled jobs', function (): void {
    $this->actingAdmin();
    Config::set('maddraxikon.features.sync_enabled', true);
    Config::set('maddraxikon.features.awards_enabled', true);
    Queue::fake();
    Livewire::test(MaddraxikonAdmin::class)->call('dispatchSync')->assertDispatched('toast', type: 'success', title: 'Maddraxikon-Synchronisation eingereiht')->call('dispatchEvaluation')->assertDispatched('toast', type: 'success', title: 'Maddraxikon-Auswertung eingereiht');
    Queue::assertPushed(SyncMaddraxikonContributions::class, fn (SyncMaddraxikonContributions $job): bool => ! $job->force);
    Queue::assertPushed(EvaluateMaddraxikonContributions::class, fn (EvaluateMaddraxikonContributions $job): bool => ! $job->force);
});

test('disabled manual actions do not dispatch jobs', function (): void {
    $this->actingAdmin();
    Config::set('maddraxikon.features.sync_enabled', false);
    Config::set('maddraxikon.features.awards_enabled', false);
    Queue::fake();
    Livewire::test(MaddraxikonAdmin::class)->call('dispatchSync')->assertDispatched('toast', type: 'warning', title: 'Synchronisation ist deaktiviert')->call('dispatchEvaluation')->assertDispatched('toast', type: 'warning', title: 'Baxx-Auswertung ist deaktiviert');
    Queue::assertNothingPushed();
});

test('open recovery alarm is visible and blocks manual evaluation', function (): void {
    $this->actingAdmin();
    Config::set('maddraxikon.features.awards_enabled', true);
    Queue::fake();
    MaddraxikonSyncState::factory()->create(['watermark_at' => now()->subDays(31), 'recovery_required_at' => now(), 'recovery_from_at' => now()->subDays(31), 'recovery_until_at' => now()->subMinute(), 'last_error' => 'Recovery-Testalarm']);
    Livewire::test(MaddraxikonAdmin::class)->assertSee('Recovery erforderlich')->assertSee('Baxx-Auswertung gesperrt')->assertSee('maddraxikon:recover')->call('dispatchEvaluation')->assertDispatched('toast', type: 'warning', title: 'Auswertung bis zum Recovery-Abschluss gesperrt');
    Queue::assertNotPushed(EvaluateMaddraxikonContributions::class);
});

test('awarded event requires reason and is reversed with audit data', function (): void {
    $admin = $this->actingAdmin();
    $member = $this->createUserWithRole(Role::Mitglied);
    $link = MaddraxikonAccountLink::factory()->create(['user_id' => $member->id]);
    $contribution = MaddraxikonContribution::factory()->create(['account_link_id' => $link->id, 'user_id' => $member->id, 'wiki_user_id' => $link->wiki_user_id, 'wiki_username' => $link->wiki_username, 'status' => MaddraxikonContributionStatus::Awarded]);
    $originalPoint = UserPoint::query()->create(['user_id' => $member->id, 'team_id' => Team::membersTeam()->id, 'points' => 4]);
    $rewardEvent = MaddraxikonRewardEvent::factory()->create(['source_contribution_id' => $contribution->id, 'user_id' => $member->id, 'account_link_id' => $link->id, 'source_revision_id' => $contribution->revision_id, 'status' => MaddraxikonRewardEventStatus::Awarded, 'candidate_points' => 4, 'awarded_points' => 4, 'user_point_id' => $originalPoint->id, 'awarded_at' => now()]);
    $apiClient = Mockery::mock(MaddraxikonApiClient::class);
    $this->app->instance(MaddraxikonRewardService::class, new MaddraxikonRewardService($apiClient));
    Livewire::test(MaddraxikonAdmin::class)->call('openReversal', $rewardEvent->id)->assertSet('showReversalModal', true)->assertSet('reversingRewardEventId', $rewardEvent->id)->set('reversalReason', '   ')->call('reverseRewardEvent')->assertHasErrors(['reversalReason' => 'required'])->set('reversalReason', 'Doppelte Gutschrift nach manueller Prüfung')->call('reverseRewardEvent')->assertHasNoErrors()->assertSet('showReversalModal', false)->assertSet('reversingRewardEventId', null)->assertDispatched('toast', type: 'success', title: 'Baxx-Gutschrift gegengebucht');
    $rewardEvent->refresh();
    expect($rewardEvent->status)->toBe(MaddraxikonRewardEventStatus::Reversed)
        ->and($rewardEvent->reversed_by)->toBe($admin->id)
        ->and($rewardEvent->reversal_reason)->toBe('Doppelte Gutschrift nach manueller Prüfung')
        ->and($rewardEvent->reversal_user_point_id)->not->toBeNull();
    $this->assertDatabaseHas('user_points', ['id' => $rewardEvent->reversal_user_point_id, 'user_id' => $member->id, 'team_id' => Team::membersTeam()->id, 'points' => -4]);
});

test('reversal service conflict is shown in modal', function (): void {
    $this->actingAdmin();
    $rewardEvent = $this->awardedRewardEvent();
    $rewardService = Mockery::mock(MaddraxikonRewardService::class);
    $rewardService->shouldReceive('reverse')->once()->andThrow(new \LogicException('Bereits gegengebucht.'));
    $this->app->instance(MaddraxikonRewardService::class, $rewardService);
    Livewire::test(MaddraxikonAdmin::class)->call('openReversal', $rewardEvent->id)->set('reversalReason', 'Prüfung durch Administration')->call('reverseRewardEvent')->assertHasErrors(['reversalReason'])->assertSet('showReversalModal', true);
});

test('technical failures can be filtered and retried individually', function (): void {
    $this->actingAdmin();
    Config::set('maddraxikon.features.awards_enabled', true);
    Config::set('maddraxikon.base_url', 'https://de.maddraxikon.com');
    Queue::fake();
    $member = $this->createUserWithRole(Role::Mitglied);
    $link = MaddraxikonAccountLink::factory()->create(['user_id' => $member->id, 'wiki_username' => 'RetryWikiUser']);
    $technical = MaddraxikonContribution::factory()->create(['account_link_id' => $link->id, 'user_id' => $member->id, 'wiki_user_id' => $link->wiki_user_id, 'wiki_username' => $link->wiki_username, 'page_title' => 'Technischer Retry-Artikel', 'status' => MaddraxikonContributionStatus::Pending, 'eligible_after' => now()->subMinute(), 'evaluation_attempts' => 2, 'last_evaluation_error' => 'MaddraxikonApiException: Timeout', 'last_evaluation_error_at' => now()->subMinute()]);
    MaddraxikonContribution::factory()->create(['account_link_id' => $link->id, 'user_id' => $member->id, 'wiki_user_id' => $link->wiki_user_id, 'wiki_username' => $link->wiki_username, 'page_title' => 'Normal ausstehender Artikel', 'status' => MaddraxikonContributionStatus::Pending, 'eligible_after' => now()->subMinute()]);
    Livewire::test(MaddraxikonAdmin::class)->set('contributionStatusFilter', 'technical')->assertSee('Technischer Retry-Artikel')->assertDontSee('Normal ausstehender Artikel')->assertSee('Technisch fehlgeschlagen')->assertSeeHtml('Special%3ADiff%2F'.$technical->revision_id)->call('retryContribution', $technical->id)->assertDispatched('toast', type: 'success', title: 'Gezielte Prüfung eingereiht');
    Queue::assertPushed(EvaluateMaddraxikonContributions::class, fn (EvaluateMaddraxikonContributions $job): bool => $job->contributionId === $technical->id && ! $job->force);
});

test('link and reward filters limit the displayed rows', function (): void {
    $this->actingAdmin();
    $activeMember = $this->createUserWithRole(Role::Mitglied);
    $disconnectedMember = $this->createUserWithRole(Role::Mitglied);
    $activeLink = MaddraxikonAccountLink::factory()->create(['user_id' => $activeMember->id, 'wiki_username' => 'NurAktiverWikiUser']);
    MaddraxikonAccountLink::factory()->disconnected()->create(['user_id' => $disconnectedMember->id, 'wiki_username' => 'NurGetrennterWikiUser']);
    Livewire::test(MaddraxikonAdmin::class)->set('linkStatusFilter', 'disconnected')->assertSee('NurGetrennterWikiUser')->assertDontSee('NurAktiverWikiUser');
    MaddraxikonRewardEvent::factory()->create(['user_id' => $activeMember->id, 'account_link_id' => $activeLink->id, 'status' => MaddraxikonRewardEventStatus::Awarded, 'status_reason' => 'NurAwardedGrund']);
    MaddraxikonRewardEvent::factory()->create(['user_id' => $activeMember->id, 'account_link_id' => $activeLink->id, 'status' => MaddraxikonRewardEventStatus::Reversed, 'status_reason' => 'NurReversedGrund', 'reversed_at' => now(), 'reversal_reason' => 'Test-Gegenbuchung']);
    Livewire::test(MaddraxikonAdmin::class)->set('rewardStatusFilter', 'reversed')->assertSee('NurReversedGrund')->assertDontSee('NurAwardedGrund');
});

test('admin can save sorted policy draft with tiers', function (): void {
    $admin = $this->actingAdmin();
    $effectiveFrom = now(config('maddraxikon.timezone', 'Europe/Berlin'))->addDay()->startOfHour();
    Livewire::actingAs($admin)->test(MaddraxikonAdmin::class)->call('openCreatePolicy')->set('policyName', 'Sommerstaffel')->set('policyEffectiveFrom', $effectiveFrom->format('Y-m-d\TH:i'))->set('policyTiers', [['minimum_added_bytes' => 500, 'points' => 3], ['minimum_added_bytes' => 100, 'points' => 1]])->set('policyNewArticleMinimumBytes', 750)->set('policyNewArticlePoints', 6)->call('savePolicyDraft')->assertHasNoErrors()->assertSet('showPolicyModal', false);
    $policy = MaddraxikonRewardPolicy::query()->sole();
    expect($policy->status)->toBe(MaddraxikonRewardPolicy::STATUS_DRAFT)
        ->and($policy->name)->toBe('Sommerstaffel')
        ->and($policy->created_by)->toBe($admin->id)
        ->and($policy->effective_from_epoch)->toBe($effectiveFrom->utc()->getTimestamp())
        ->and($policy->tiers()->pluck('minimum_added_bytes')->all())->toBe([100, 500]);
});

test('admin can publish future policy and ui shows planned version', function (): void {
    $admin = $this->actingAdmin();
    $effectiveFrom = now(config('maddraxikon.timezone', 'Europe/Berlin'))->addDay()->startOfHour();
    Livewire::actingAs($admin)->test(MaddraxikonAdmin::class)->set('policyName', 'Geplante Staffel')->set('policyEffectiveFrom', $effectiveFrom->format('Y-m-d\TH:i'))->set('policyTiers', [['minimum_added_bytes' => 250, 'points' => 2]])->set('policyNewArticleMinimumBytes', 500)->set('policyNewArticlePoints', 5)->call('publishPolicy')->assertHasNoErrors()->assertSet('showPolicyModal', false)->assertSee('Geplante Staffel')->assertSee('Geplant');
    $policy = MaddraxikonRewardPolicy::query()->sole();
    expect($policy->status)->toBe(MaddraxikonRewardPolicy::STATUS_PUBLISHED)
        ->and($policy->published_by)->toBe($admin->id)
        ->and($policy->published_at)->not->toBeNull();
});

test('policy form rejects duplicate tiers and past publication', function (): void {
    $admin = $this->actingAdmin();
    Livewire::actingAs($admin)->test(MaddraxikonAdmin::class)->set('policyName', 'Doppelte Staffel')->set('policyEffectiveFrom', now(config('maddraxikon.timezone', 'Europe/Berlin'))->addDay()->format('Y-m-d\TH:i'))->set('policyTiers', [['minimum_added_bytes' => 100, 'points' => 1], ['minimum_added_bytes' => 100, 'points' => 2]])->call('savePolicyDraft')->assertOnlyHasErrors(['policyTiers.0.minimum_added_bytes', 'policyTiers.1.minimum_added_bytes']);
    Livewire::actingAs($admin)->test(MaddraxikonAdmin::class)->set('policyName', 'Vergangene Staffel')->set('policyEffectiveFrom', now(config('maddraxikon.timezone', 'Europe/Berlin'))->subHour()->format('Y-m-d\TH:i'))->set('policyTiers', [['minimum_added_bytes' => 100, 'points' => 1]])->call('publishPolicy')->assertOnlyHasErrors(['policyEffectiveFrom']);
});

test('admin can copy published policy without mutating original', function (): void {
    $admin = $this->actingAdmin();
    $policy = MaddraxikonRewardPolicy::factory()->create(['name' => 'Originalstaffel', 'effective_from' => now()->subDay(), 'created_by' => $admin->id]);
    MaddraxikonRewardPolicyTier::factory()->create(['maddraxikon_reward_policy_id' => $policy->id, 'minimum_added_bytes' => 400, 'points' => 4]);
    $policy->update(['status' => MaddraxikonRewardPolicy::STATUS_PUBLISHED, 'published_by' => $admin->id, 'published_at' => now()->subDay()]);
    Livewire::actingAs($admin)->test(MaddraxikonAdmin::class)->call('copyPolicy', $policy->id)->assertSet('editingPolicyId', null)->assertSet('policyName', 'Kopie von Originalstaffel')->assertSet('policyTiers.0.minimum_added_bytes', 400)->assertSet('policyTiers.0.points', 4)->assertSet('showPolicyModal', true);
    expect($policy->fresh()->name)->toBe('Originalstaffel');
});
