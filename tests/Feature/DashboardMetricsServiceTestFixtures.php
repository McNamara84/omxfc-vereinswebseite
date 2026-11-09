<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait DashboardMetricsServiceTestFixtures
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Team}
     */
    private function member(Role $role = Role::Mitglied): array
    {
        $team = Team::membersTeam();

        return [$this->attachMember($team, $role), $team];
    }

    private function attachMember(Team $team, Role $role = Role::Mitglied): User
    {
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => $role->value]);

        return $member;
    }

    /** @return array<string, mixed> */
    private function build(User $user, Team $team, Role $role = Role::Mitglied): array
    {
        return app(DashboardMetricsService::class)->build($user, $team, $role, 0);
    }

    /** @return array<string, mixed> */
    private function metric(array $dashboard, string $key): array
    {
        return collect($dashboard['metricGroups'])->flatMap(fn (array $group): array => $group['metrics'])->firstWhere('key', $key);
    }

    private function activity(User $user): Activity
    {
        return Activity::create(['user_id' => $user->id, 'subject_type' => User::class, 'subject_id' => $user->id, 'action' => 'member_approved']);
    }

    private function timestamp(Model $model, mixed $timestamp): void
    {
        $model->forceFill(['created_at' => $timestamp, 'updated_at' => $timestamp])->saveQuietly();
    }
}
