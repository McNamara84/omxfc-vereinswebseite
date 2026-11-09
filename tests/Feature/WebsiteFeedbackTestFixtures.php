<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Services\WebsiteFeedback\FeedbackDeliveryService;
use App\Services\WebsiteFeedback\FeedbackSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\CreatesUserWithRole;

trait WebsiteFeedbackTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function allowFeedbackSession(bool $available = true, bool $expectSubmitted = false): void
    {
        $sessions = Mockery::mock(FeedbackSessionService::class);
        $sessions->shouldReceive('register')->zeroOrMoreTimes();
        $sessions->shouldReceive('isAvailable')->zeroOrMoreTimes()->andReturn($available);
        if ($expectSubmitted) {
            $sessions->shouldReceive('markSubmitted')->once();
        } else {
            $sessions->shouldNotReceive('markSubmitted');
        }
        $this->app->instance(FeedbackSessionService::class, $sessions);
    }

    private function mockDeliveryWithoutCalls(): void
    {
        $delivery = Mockery::mock(FeedbackDeliveryService::class);
        $delivery->shouldNotReceive('queue');
        $this->app->instance(FeedbackDeliveryService::class, $delivery);
    }

    private function removeSeededManagement(): void
    {
        DB::table('team_user')->where('team_id', Team::membersTeam()->id)->whereIn('role', [Role::Admin->value, Role::Vorstand->value])->delete();
    }
}
