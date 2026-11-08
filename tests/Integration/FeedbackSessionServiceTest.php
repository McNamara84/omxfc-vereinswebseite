<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\WebsiteFeedback\FeedbackSessionService;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(FeedbackSessionServiceTestFixtures::class);

test('it counts a session only once and marks it submitted', function (): void {
    $user = $this->member(Role::Mitglied);
    $session = $this->newSession(1);
    $service = app(FeedbackSessionService::class);
    $service->register($user, $session);
    $service->register($user, $session);
    expect($user->fresh()->website_feedback_session_count)->toBe(1)
        ->and($service->isAvailable($user, $session))->toBeTrue();
    $service->markSubmitted($user, $session);
    expect($service->isAvailable($user, $session))->toBeFalse()
        ->and($service->state($session)['submitted'])->toBeTrue();
});

test('it selects the first and then every nth session', function (): void {
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
    expect($visibleSessions)->toBe([1, 6, 11])
        ->and($user->fresh()->website_feedback_session_count)->toBe(11);
});

test('it reuses the atomic registration for parallel requests of the same session', function (): void {
    $user = $this->member(Role::Mitglied);
    $firstRequestSession = $this->newSession(42);
    $parallelRequestSession = $this->newSession(42);
    $service = app(FeedbackSessionService::class);
    $service->register($user, $firstRequestSession);
    $service->register($user, $parallelRequestSession);
    expect($user->fresh()->website_feedback_session_count)->toBe(1)
        ->and($service->state($parallelRequestSession)['session_count'])->toBe(1)
        ->and($service->isAvailable($user, $parallelRequestSession))->toBeTrue();
});

test('it ignores guests without a membership role and applicants', function (): void {
    $team = Team::membersTeam();
    $userWithoutMembership = User::factory()->create();
    $applicant = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($applicant, ['role' => Role::Anwaerter->value]);
    $service = app(FeedbackSessionService::class);
    $sessionWithoutMembership = $this->newSession(1);
    $applicantSession = $this->newSession(2);
    $service->register($userWithoutMembership, $sessionWithoutMembership);
    $service->register($applicant, $applicantSession);
    expect($service->state($sessionWithoutMembership))->toBe([])
        ->and($service->state($applicantSession))->toBe([])
        ->and($userWithoutMembership->fresh()->website_feedback_session_count)->toBe(0)
        ->and($applicant->fresh()->website_feedback_session_count)->toBe(0);
});

test('invalid interval safely behaves like every session', function (): void {
    config(['feedback.session_interval' => 0]);
    $user = $this->member(Role::Mitglied);
    $service = app(FeedbackSessionService::class);
    foreach ([1, 2, 3] as $number) {
        $session = $this->newSession($number);
        $service->register($user, $session);
        expect($service->isAvailable($user, $session))->toBeTrue();
    }
});
