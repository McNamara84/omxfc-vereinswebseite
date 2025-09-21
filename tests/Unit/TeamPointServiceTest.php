<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\TeamPointService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPointServiceTest extends TestCase
{
    use RefreshDatabase;

    private TeamPointService $service;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TeamPointService();
        $this->team = Team::membersTeam();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function memberWithPoints(int $points = 0): User
    {
        $user = User::factory()->create(['current_team_id' => $this->team->id]);
        $this->team->users()->attach($user, ['role' => Role::Mitglied->value]);
        if ($points > 0) {
            $this->addPoints($user, $points, Carbon::now());
        }
        return $user;
    }

    private function addPoints(User $user, int $points, Carbon $createdAt): void
    {
        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $this->team->id,
            'points' => $points,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function test_get_user_points_returns_team_points(): void
    {
        $user = $this->memberWithPoints(7);
        $this->assertSame(7, $this->service->getUserPoints($user));
    }

    public function test_assert_min_points_passes_when_enough(): void
    {
        $user = $this->memberWithPoints(10);
        $this->actingAs($user);
        $this->service->assertMinPoints(5);
        $this->assertTrue(true);
    }

    public function test_assert_min_points_throws_when_insufficient(): void
    {
        $user = $this->memberWithPoints(2);
        $this->actingAs($user);
        $this->expectException(AuthorizationException::class);
        $this->service->assertMinPoints(5);
    }

    public function test_get_user_point_trend_returns_last_seven_days(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 1, 10, 12));
        $user = $this->memberWithPoints();

        $this->addPoints($user, 5, Carbon::now()->subDays(1));
        $this->addPoints($user, 3, Carbon::now()->subDays(3));

        $trend = $this->service->getUserPointTrend($user, $this->team);

        $this->assertCount(7, $trend);
        $trendByDate = collect($trend)->keyBy('date');
        $this->assertSame(5, $trendByDate[Carbon::now()->subDays(1)->toDateString()]['points']);
        $this->assertSame(3, $trendByDate[Carbon::now()->subDays(3)->toDateString()]['points']);
        $this->assertSame(0, $trendByDate[Carbon::now()->subDays(2)->toDateString()]['points']);
    }

    public function test_dashboard_metrics_calculates_team_average_and_progress(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 1, 10, 12));

        $user = $this->memberWithPoints();
        $otherOne = $this->memberWithPoints();
        $otherTwo = $this->memberWithPoints();

        $this->addPoints($user, 12, Carbon::now());
        $this->addPoints($otherOne, 18, Carbon::now());
        // $otherTwo intentionally keeps zero points to check averaging with non-contributors.

        $metrics = $this->service->getDashboardMetrics($user, $this->team);

        $this->assertSame(10.0, $metrics['team_average']);
        $this->assertSame(100, $metrics['team_average_progress']);
        $this->assertSame(12, $metrics['weekly']['total']);
        $this->assertSame(24, $metrics['weekly']['progress']);
    }

    public function test_dashboard_metrics_exposes_leaderboard_and_rank_gap(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 1, 10, 12));

        $user = $this->memberWithPoints();
        $leader = $this->memberWithPoints();
        $second = $this->memberWithPoints();
        $third = $this->memberWithPoints();

        $this->addPoints($leader, 60, Carbon::now());
        $this->addPoints($second, 40, Carbon::now());
        $this->addPoints($third, 20, Carbon::now());
        $this->addPoints($user, 5, Carbon::now());

        $metrics = $this->service->getDashboardMetrics($user, $this->team);

        $this->assertSame(4, $metrics['user_rank']);
        $this->assertSame(15, $metrics['points_to_next_rank']);
        $this->assertSame(20, $metrics['next_rank_points']);

        $highlight = collect($metrics['leaderboard'])->firstWhere('is_current_user');
        $this->assertNotNull($highlight);
        $this->assertSame($user->name, $highlight['name']);
        $this->assertTrue($highlight['is_additional'] ?? false);
        $this->assertSame(5, $highlight['points']);
    }
}

