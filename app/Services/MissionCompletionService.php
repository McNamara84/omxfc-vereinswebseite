<?php

namespace App\Services;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class MissionCompletionService
{
    public function __construct(private readonly MaddraxiversumBaxxService $baxx) {}

    public function status(User $user): string
    {
        return DB::transaction(function () use ($user): string {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $mission = Mission::query()->where('user_id', $user->id)->where('completed', false)->lockForUpdate()->first();
            if (! $mission) {
                return 'none';
            }
            $duration = $mission->travel_duration * 2 + $mission->mission_duration;
            if (now()->greaterThanOrEqualTo($mission->started_at->copy()->addSeconds($duration))) {
                $mission->update(['completed' => true]);
                $count = Mission::query()->where('user_id', $user->id)->where('completed', true)->count();
                $this->baxx->awardPointsForMission($user, $mission, $count);

                return 'completed';
            }

            return now()->greaterThanOrEqualTo($mission->arrival_at) ? 'in_mission' : 'traveling';
        }, 3);
    }
}
