<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\WebsiteFeedback\FeedbackSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\TestCase;

class FeedbackSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_a_session_only_once_and_marks_it_submitted(): void
    {
        $user = $this->member(Role::Mitglied);
        $session = $this->newSession(1);
        $service = app(FeedbackSessionService::class);

        $service->register($user, $session);
        $service->register($user, $session);

        $this->assertSame(1, $user->fresh()->website_feedback_session_count);
        $this->assertTrue($service->isAvailable($user, $session));

        $service->markSubmitted($user, $session);

        $this->assertFalse($service->isAvailable($user, $session));
        $this->assertTrue($service->state($session)['submitted']);
    }

    public function test_it_selects_the_first_and_then_every_nth_session(): void
    {
        config(['feedback.session_interval' => 5]);
        $user = $this->member(Role::Mitglied);
        $service = app(FeedbackSessionService::class);
        $visibleSessions = [];

        for ($number = 1; $number <= 11; $number++) {
            $session = $this->newSession($number);
            $service->register($user, $session);

            if ($service->isAvailable($user, $session)) {
                $visibleSessions[] = $number;
            }
        }

        $this->assertSame([1, 6, 11], $visibleSessions);
        $this->assertSame(11, $user->fresh()->website_feedback_session_count);
    }

    public function test_it_reuses_the_atomic_registration_for_parallel_requests_of_the_same_session(): void
    {
        $user = $this->member(Role::Mitglied);
        $firstRequestSession = $this->newSession(42);
        $parallelRequestSession = $this->newSession(42);
        $service = app(FeedbackSessionService::class);

        $service->register($user, $firstRequestSession);
        $service->register($user, $parallelRequestSession);

        $this->assertSame(1, $user->fresh()->website_feedback_session_count);
        $this->assertSame(1, $service->state($parallelRequestSession)['session_count']);
        $this->assertTrue($service->isAvailable($user, $parallelRequestSession));
    }

    public function test_it_ignores_guests_without_a_membership_role_and_applicants(): void
    {
        $team = Team::membersTeam();
        $userWithoutMembership = User::factory()->create();
        $applicant = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($applicant, ['role' => Role::Anwaerter->value]);
        $service = app(FeedbackSessionService::class);

        $sessionWithoutMembership = $this->newSession(1);
        $applicantSession = $this->newSession(2);
        $service->register($userWithoutMembership, $sessionWithoutMembership);
        $service->register($applicant, $applicantSession);

        $this->assertSame([], $service->state($sessionWithoutMembership));
        $this->assertSame([], $service->state($applicantSession));
        $this->assertSame(0, $userWithoutMembership->fresh()->website_feedback_session_count);
        $this->assertSame(0, $applicant->fresh()->website_feedback_session_count);
    }

    public function test_invalid_interval_safely_behaves_like_every_session(): void
    {
        config(['feedback.session_interval' => 0]);
        $user = $this->member(Role::Mitglied);
        $service = app(FeedbackSessionService::class);

        foreach ([1, 2, 3] as $number) {
            $session = $this->newSession($number);
            $service->register($user, $session);
            $this->assertTrue($service->isAvailable($user, $session));
        }
    }

    private function member(Role $role): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    private function newSession(int $number): Store
    {
        $id = str_pad((string) $number, 40, '0', STR_PAD_LEFT);
        $session = new Store('website-feedback-test', new ArraySessionHandler(120), $id);
        $session->start();

        return $session;
    }
}
