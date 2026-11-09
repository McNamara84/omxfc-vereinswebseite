<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait DashboardActivityFeedTestFixtures
{
    use RefreshDatabase;

    private function member(): User
    {
        $team = Team::membersTeam();
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        return $member;
    }

    /**
     * @return array<int, int>
     */
    private function activities(User $member, int $count): array
    {
        $ids = [];
        for ($index = 0; $index < $count; $index++) {
            $ids[] = $this->activity($member, User::class, now()->subMinutes($index))->id;
        }

        return $ids;
    }

    /**
     * @param  class-string  $subjectType
     */
    private function activity(User $member, string $subjectType, mixed $createdAt = null, string $action = 'member_approved'): Activity
    {
        $activity = Activity::query()->create(['user_id' => $member->id, 'subject_type' => $subjectType, 'subject_id' => $member->id, 'action' => $action]);
        if ($createdAt !== null) {
            $activity->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
        }

        return $activity->fresh();
    }
}
