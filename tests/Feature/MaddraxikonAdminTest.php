<?php

namespace Tests\Feature;

use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonContributionType;
use App\Enums\MaddraxikonRewardEventStatus;
use App\Enums\Role;
use App\Jobs\EvaluateMaddraxikonContributions;
use App\Jobs\SyncMaddraxikonContributions;
use App\Livewire\MaddraxikonAdmin;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardEvent;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use App\Models\UserPoint;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use App\Services\Maddraxikon\MaddraxikonNamespaceHealthService;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class MaddraxikonAdminTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.testing_minimal_layout', true);
    }

    public function test_route_is_available_only_to_admins(): void
    {
        $url = route('rewards.admin.maddraxikon', absolute: false);
        $route = Route::getRoutes()->getByName('rewards.admin.maddraxikon');

        $this->assertNotNull($route);
        $this->assertSame('belohnungen/admin/maddraxikon', $route->uri());
        $this->assertContains('maddraxikon.admin', $route->gatherMiddleware());
        $this->get($url)->assertRedirect('/login');

        $member = $this->actingMember();
        $this->actingAs($member)->get($url)->assertForbidden();

        $admin = $this->actingAdmin();
        $this->actingAs($admin)
            ->get($url)
            ->assertOk()
            ->assertSee('Maddraxikon-Baxx');
    }

    public function test_admin_role_in_an_unrelated_team_grants_no_access(): void
    {
        $url = route('rewards.admin.maddraxikon', absolute: false);
        $user = $this->createUserWithRole(Role::Mitglied);
        $unrelatedTeam = Team::factory()->create([
            'name' => 'Unabhängiges Team',
            'personal_team' => false,
        ]);
        $unrelatedTeam->users()->attach($user, [
            'role' => Role::Admin->value,
        ]);
        $user->forceFill([
            'current_team_id' => $unrelatedTeam->id,
        ])->save();

        $this->actingAs($user->fresh())
            ->get($url)
            ->assertForbidden();
    }

    public function test_members_team_admin_keeps_access_after_switching_teams(): void
    {
        $url = route('rewards.admin.maddraxikon', absolute: false);
        $admin = $this->createUserWithRole(Role::Admin);
        $workingGroup = Team::factory()->create([
            'name' => 'Arbeitsgruppe Test',
            'personal_team' => false,
        ]);
        $workingGroup->users()->attach($admin, [
            'role' => Role::Mitglied->value,
        ]);
        $admin->forceFill([
            'current_team_id' => $workingGroup->id,
        ])->save();

        $this->actingAs($admin->fresh())
            ->get($url)
            ->assertOk()
            ->assertSee('Maddraxikon-Baxx');

        Config::set('maddraxikon.features.sync_enabled', true);
        Queue::fake();
        Livewire::actingAs($admin->fresh())
            ->test(MaddraxikonAdmin::class)
            ->call('dispatchSync')
            ->assertHasNoErrors();
        Queue::assertPushed(SyncMaddraxikonContributions::class);
    }

    public function test_role_revocation_blocks_subsequent_livewire_actions(): void
    {
        Config::set('maddraxikon.features.sync_enabled', true);
        Queue::fake();
        $admin = $this->actingAdmin();
        $component = Livewire::test(MaddraxikonAdmin::class);

        $admin->currentTeam->users()->updateExistingPivot($admin->id, [
            'role' => Role::Mitglied->value,
        ]);

        $component->call('dispatchSync')->assertForbidden();
        Queue::assertNotPushed(SyncMaddraxikonContributions::class);
    }

    public function test_dashboard_reads_local_status_without_namespace_request(): void
    {
        $this->actingAdmin();
        Config::set('maddraxikon.features.linking_enabled', true);
        Config::set('maddraxikon.features.sync_enabled', false);
        Config::set('maddraxikon.features.awards_enabled', true);

        MaddraxikonSyncState::factory()->create([
            'watermark_at' => now()->subMinute(),
            'last_succeeded_at' => now()->subMinutes(2),
            'last_imported_count' => 12,
            'consecutive_failures' => 2,
            'last_error' => 'Testfehler beim Import',
        ]);

        $member = $this->createUserWithRole(Role::Mitglied);
        $member->forceFill(['name' => 'Lokales Testmitglied'])->save();
        $link = MaddraxikonAccountLink::factory()->create([
            'user_id' => $member->id,
            'wiki_username' => 'WikiTester',
        ]);

        foreach (MaddraxikonContributionStatus::cases() as $status) {
            MaddraxikonContribution::factory()->create([
                'account_link_id' => $link->id,
                'user_id' => $member->id,
                'wiki_user_id' => $link->wiki_user_id,
                'wiki_username' => $link->wiki_username,
                'status' => $status,
            ]);
        }

        $article = MaddraxikonContribution::factory()
            ->newArticle()
            ->create([
                'account_link_id' => $link->id,
                'user_id' => $member->id,
                'wiki_user_id' => $link->wiki_user_id,
                'wiki_username' => $link->wiki_username,
                'status' => MaddraxikonContributionStatus::Awarded,
            ]);
        MaddraxikonRewardEvent::factory()->create([
            'source_contribution_id' => $article->id,
            'user_id' => $member->id,
            'account_link_id' => $link->id,
            'source_revision_id' => $article->revision_id,
            'action_key' => MaddraxikonRewardEvent::ACTION_NEW_ARTICLE,
            'status' => MaddraxikonRewardEventStatus::Awarded,
            'awarded_points' => 5,
            'awarded_at' => now(),
        ]);

        $healthService = Mockery::mock(
            MaddraxikonNamespaceHealthService::class,
        );
        $healthService->shouldNotReceive('check');
        $this->app->instance(
            MaddraxikonNamespaceHealthService::class,
            $healthService,
        );

        Livewire::test(MaddraxikonAdmin::class)
            ->assertSet('namespaceHealth', null)
            ->assertSee('OAuth-Verknüpfung')
            ->assertSee('Aktiviert')
            ->assertSee('Deaktiviert')
            ->assertSee('Testfehler beim Import')
            ->assertSee('Lokales Testmitglied')
            ->assertSee('WikiTester')
            ->assertSee('Neuer Artikel')
            ->assertSee('Ausstehend')
            ->assertSee('Qualifiziert')
            ->assertSee('Abgelehnt')
            ->assertSee('Gutgeschrieben');
    }

    public function test_namespace_check_runs_only_on_explicit_action(): void
    {
        $this->actingAdmin();

        $result = [
            'healthy' => false,
            'expected' => [0 => '', 10 => 'Vorlage'],
            'actual' => [0 => '', 10 => 'Template'],
            'missing' => [],
            'mismatched' => [
                10 => [
                    'expected' => 'Vorlage',
                    'actual' => 'Template',
                ],
            ],
        ];
        $healthService = Mockery::mock(
            MaddraxikonNamespaceHealthService::class,
        );
        $healthService->shouldReceive('check')
            ->once()
            ->andReturn($result);
        $this->app->instance(
            MaddraxikonNamespaceHealthService::class,
            $healthService,
        );

        Livewire::test(MaddraxikonAdmin::class)
            ->assertSet('namespaceHealth', null)
            ->call('checkNamespaces')
            ->assertSet('namespaceHealth.healthy', false)
            ->assertSee('Namensraum-Abweichungen')
            ->assertSee('Vorlage')
            ->assertSee('Template')
            ->assertDispatched(
                'toast',
                type: 'warning',
                title: 'Namensraum-Abweichungen gefunden',
            );
    }

    public function test_namespace_check_reports_remote_failure_without_breaking_dashboard(): void
    {
        $this->actingAdmin();

        $healthService = Mockery::mock(
            MaddraxikonNamespaceHealthService::class,
        );
        $healthService->shouldReceive('check')
            ->once()
            ->andThrow(new RuntimeException('Wiki nicht erreichbar'));
        $this->app->instance(
            MaddraxikonNamespaceHealthService::class,
            $healthService,
        );

        Livewire::test(MaddraxikonAdmin::class)
            ->call('checkNamespaces')
            ->assertSet('namespaceHealth', null)
            ->assertSet(
                'namespaceHealthError',
                'Die Namensräume konnten nicht geprüft werden. Details stehen im Anwendungsprotokoll.',
            )
            ->assertSee('Die Namensräume konnten nicht geprüft werden.')
            ->assertDispatched(
                'toast',
                type: 'error',
                title: 'Namensraum-Prüfung fehlgeschlagen',
            );
    }

    public function test_manual_actions_dispatch_enabled_jobs(): void
    {
        $this->actingAdmin();
        Config::set('maddraxikon.features.sync_enabled', true);
        Config::set('maddraxikon.features.awards_enabled', true);
        Queue::fake();

        Livewire::test(MaddraxikonAdmin::class)
            ->call('dispatchSync')
            ->assertDispatched(
                'toast',
                type: 'success',
                title: 'Maddraxikon-Synchronisation eingereiht',
            )
            ->call('dispatchEvaluation')
            ->assertDispatched(
                'toast',
                type: 'success',
                title: 'Maddraxikon-Auswertung eingereiht',
            );

        Queue::assertPushed(
            SyncMaddraxikonContributions::class,
            fn (SyncMaddraxikonContributions $job): bool => ! $job->force,
        );
        Queue::assertPushed(
            EvaluateMaddraxikonContributions::class,
            fn (EvaluateMaddraxikonContributions $job): bool => ! $job->force,
        );
    }

    public function test_disabled_manual_actions_do_not_dispatch_jobs(): void
    {
        $this->actingAdmin();
        Config::set('maddraxikon.features.sync_enabled', false);
        Config::set('maddraxikon.features.awards_enabled', false);
        Queue::fake();

        Livewire::test(MaddraxikonAdmin::class)
            ->call('dispatchSync')
            ->assertDispatched(
                'toast',
                type: 'warning',
                title: 'Synchronisation ist deaktiviert',
            )
            ->call('dispatchEvaluation')
            ->assertDispatched(
                'toast',
                type: 'warning',
                title: 'Baxx-Auswertung ist deaktiviert',
            );

        Queue::assertNothingPushed();
    }

    public function test_open_recovery_alarm_is_visible_and_blocks_manual_evaluation(): void
    {
        $this->actingAdmin();
        Config::set('maddraxikon.features.awards_enabled', true);
        Queue::fake();
        MaddraxikonSyncState::factory()->create([
            'watermark_at' => now()->subDays(31),
            'recovery_required_at' => now(),
            'recovery_from_at' => now()->subDays(31),
            'recovery_until_at' => now()->subMinute(),
            'last_error' => 'Recovery-Testalarm',
        ]);

        Livewire::test(MaddraxikonAdmin::class)
            ->assertSee('Recovery erforderlich')
            ->assertSee('Baxx-Auswertung gesperrt')
            ->assertSee('maddraxikon:recover')
            ->call('dispatchEvaluation')
            ->assertDispatched(
                'toast',
                type: 'warning',
                title: 'Auswertung bis zum Recovery-Abschluss gesperrt',
            );

        Queue::assertNotPushed(EvaluateMaddraxikonContributions::class);
    }

    public function test_awarded_event_requires_reason_and_is_reversed_with_audit_data(): void
    {
        $admin = $this->actingAdmin();
        $member = $this->createUserWithRole(Role::Mitglied);
        $link = MaddraxikonAccountLink::factory()->create([
            'user_id' => $member->id,
        ]);
        $contribution = MaddraxikonContribution::factory()->create([
            'account_link_id' => $link->id,
            'user_id' => $member->id,
            'wiki_user_id' => $link->wiki_user_id,
            'wiki_username' => $link->wiki_username,
            'status' => MaddraxikonContributionStatus::Awarded,
        ]);
        $originalPoint = UserPoint::query()->create([
            'user_id' => $member->id,
            'team_id' => Team::membersTeam()->id,
            'points' => 4,
        ]);
        $rewardEvent = MaddraxikonRewardEvent::factory()->create([
            'source_contribution_id' => $contribution->id,
            'user_id' => $member->id,
            'account_link_id' => $link->id,
            'source_revision_id' => $contribution->revision_id,
            'status' => MaddraxikonRewardEventStatus::Awarded,
            'candidate_points' => 4,
            'awarded_points' => 4,
            'user_point_id' => $originalPoint->id,
            'awarded_at' => now(),
        ]);

        $apiClient = Mockery::mock(MaddraxikonApiClient::class);
        $this->app->instance(
            MaddraxikonRewardService::class,
            new MaddraxikonRewardService($apiClient),
        );

        Livewire::test(MaddraxikonAdmin::class)
            ->call('openReversal', $rewardEvent->id)
            ->assertSet('showReversalModal', true)
            ->assertSet('reversingRewardEventId', $rewardEvent->id)
            ->set('reversalReason', '   ')
            ->call('reverseRewardEvent')
            ->assertHasErrors(['reversalReason' => 'required'])
            ->set(
                'reversalReason',
                'Doppelte Gutschrift nach manueller Prüfung',
            )
            ->call('reverseRewardEvent')
            ->assertHasNoErrors()
            ->assertSet('showReversalModal', false)
            ->assertSet('reversingRewardEventId', null)
            ->assertDispatched(
                'toast',
                type: 'success',
                title: 'Baxx-Gutschrift gegengebucht',
            );

        $rewardEvent->refresh();

        $this->assertSame(
            MaddraxikonRewardEventStatus::Reversed,
            $rewardEvent->status,
        );
        $this->assertSame($admin->id, $rewardEvent->reversed_by);
        $this->assertSame(
            'Doppelte Gutschrift nach manueller Prüfung',
            $rewardEvent->reversal_reason,
        );
        $this->assertNotNull($rewardEvent->reversal_user_point_id);
        $this->assertDatabaseHas('user_points', [
            'id' => $rewardEvent->reversal_user_point_id,
            'user_id' => $member->id,
            'team_id' => Team::membersTeam()->id,
            'points' => -4,
        ]);
    }

    public function test_reversal_service_conflict_is_shown_in_modal(): void
    {
        $this->actingAdmin();
        $rewardEvent = $this->awardedRewardEvent();
        $rewardService = Mockery::mock(MaddraxikonRewardService::class);
        $rewardService->shouldReceive('reverse')
            ->once()
            ->andThrow(new \LogicException('Bereits gegengebucht.'));
        $this->app->instance(
            MaddraxikonRewardService::class,
            $rewardService,
        );

        Livewire::test(MaddraxikonAdmin::class)
            ->call('openReversal', $rewardEvent->id)
            ->set('reversalReason', 'Prüfung durch Administration')
            ->call('reverseRewardEvent')
            ->assertHasErrors(['reversalReason'])
            ->assertSet('showReversalModal', true);
    }

    public function test_technical_failures_can_be_filtered_and_retried_individually(): void
    {
        $this->actingAdmin();
        Config::set('maddraxikon.features.awards_enabled', true);
        Config::set('maddraxikon.base_url', 'https://de.maddraxikon.com');
        Queue::fake();
        $member = $this->createUserWithRole(Role::Mitglied);
        $link = MaddraxikonAccountLink::factory()->create([
            'user_id' => $member->id,
            'wiki_username' => 'RetryWikiUser',
        ]);
        $technical = MaddraxikonContribution::factory()->create([
            'account_link_id' => $link->id,
            'user_id' => $member->id,
            'wiki_user_id' => $link->wiki_user_id,
            'wiki_username' => $link->wiki_username,
            'page_title' => 'Technischer Retry-Artikel',
            'status' => MaddraxikonContributionStatus::Pending,
            'eligible_after' => now()->subMinute(),
            'evaluation_attempts' => 2,
            'last_evaluation_error' => 'MaddraxikonApiException: Timeout',
            'last_evaluation_error_at' => now()->subMinute(),
        ]);
        MaddraxikonContribution::factory()->create([
            'account_link_id' => $link->id,
            'user_id' => $member->id,
            'wiki_user_id' => $link->wiki_user_id,
            'wiki_username' => $link->wiki_username,
            'page_title' => 'Normal ausstehender Artikel',
            'status' => MaddraxikonContributionStatus::Pending,
            'eligible_after' => now()->subMinute(),
        ]);

        Livewire::test(MaddraxikonAdmin::class)
            ->set('contributionStatusFilter', 'technical')
            ->assertSee('Technischer Retry-Artikel')
            ->assertDontSee('Normal ausstehender Artikel')
            ->assertSee('Technisch fehlgeschlagen')
            ->assertSeeHtml(
                'Special%3ADiff%2F'.$technical->revision_id,
            )
            ->call('retryContribution', $technical->id)
            ->assertDispatched(
                'toast',
                type: 'success',
                title: 'Gezielte Prüfung eingereiht',
            );

        Queue::assertPushed(
            EvaluateMaddraxikonContributions::class,
            fn (EvaluateMaddraxikonContributions $job): bool => (
                $job->contributionId === $technical->id
                && ! $job->force
            ),
        );
    }

    public function test_link_and_reward_filters_limit_the_displayed_rows(): void
    {
        $this->actingAdmin();
        $activeMember = $this->createUserWithRole(Role::Mitglied);
        $disconnectedMember = $this->createUserWithRole(Role::Mitglied);
        $activeLink = MaddraxikonAccountLink::factory()->create([
            'user_id' => $activeMember->id,
            'wiki_username' => 'NurAktiverWikiUser',
        ]);
        MaddraxikonAccountLink::factory()->disconnected()->create([
            'user_id' => $disconnectedMember->id,
            'wiki_username' => 'NurGetrennterWikiUser',
        ]);

        Livewire::test(MaddraxikonAdmin::class)
            ->set('linkStatusFilter', 'disconnected')
            ->assertSee('NurGetrennterWikiUser')
            ->assertDontSee('NurAktiverWikiUser');

        MaddraxikonRewardEvent::factory()->create([
            'user_id' => $activeMember->id,
            'account_link_id' => $activeLink->id,
            'status' => MaddraxikonRewardEventStatus::Awarded,
            'status_reason' => 'NurAwardedGrund',
        ]);
        MaddraxikonRewardEvent::factory()->create([
            'user_id' => $activeMember->id,
            'account_link_id' => $activeLink->id,
            'status' => MaddraxikonRewardEventStatus::Reversed,
            'status_reason' => 'NurReversedGrund',
            'reversed_at' => now(),
            'reversal_reason' => 'Test-Gegenbuchung',
        ]);

        Livewire::test(MaddraxikonAdmin::class)
            ->set('rewardStatusFilter', 'reversed')
            ->assertSee('NurReversedGrund')
            ->assertDontSee('NurAwardedGrund');
    }

    private function awardedRewardEvent(): MaddraxikonRewardEvent
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $link = MaddraxikonAccountLink::factory()->create([
            'user_id' => $member->id,
        ]);
        $contribution = MaddraxikonContribution::factory()->create([
            'account_link_id' => $link->id,
            'user_id' => $member->id,
            'wiki_user_id' => $link->wiki_user_id,
            'wiki_username' => $link->wiki_username,
            'type' => MaddraxikonContributionType::Edit,
            'status' => MaddraxikonContributionStatus::Awarded,
        ]);

        return MaddraxikonRewardEvent::factory()->create([
            'source_contribution_id' => $contribution->id,
            'user_id' => $member->id,
            'account_link_id' => $link->id,
            'source_revision_id' => $contribution->revision_id,
            'status' => MaddraxikonRewardEventStatus::Awarded,
            'awarded_points' => 1,
            'awarded_at' => now(),
        ]);
    }
}
