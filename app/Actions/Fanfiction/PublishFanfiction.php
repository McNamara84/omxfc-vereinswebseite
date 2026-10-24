<?php

namespace App\Actions\Fanfiction;

use App\Enums\FanfictionStatus;
use App\Models\Activity;
use App\Models\BaxxEarningRule;
use App\Models\Fanfiction;
use App\Services\Baxx\BaxxBookingWriter;
use App\Services\FanfictionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class PublishFanfiction
{
    public function __construct(private readonly FanfictionService $rewards, private readonly BaxxBookingWriter $writer) {}

    /** Administrative entry points authorize the operation before calling this action. */
    public function execute(Fanfiction $fanfiction): bool
    {
        return DB::transaction(function () use ($fanfiction): bool {
            $story = Fanfiction::query()->lockForUpdate()->findOrFail($fanfiction->id);
            if ($story->status === FanfictionStatus::Published) {
                return false;
            }
            $story->update(['status' => FanfictionStatus::Published, 'published_at' => now()]);
            if (! $story->reward) {
                $this->rewards->createRewardForFanfiction($story);
            }
            $activity = Activity::firstOrCreate([
                'user_id' => $story->user_id ?? $story->created_by,
                'subject_type' => Fanfiction::class, 'subject_id' => $story->id, 'action' => 'published',
            ]);
            $points = BaxxEarningRule::getPointsFor('fanfiction_publish');
            $author = $story->author;
            if ($activity->wasRecentlyCreated && $story->user_id && $author?->currentTeam && $points > 0) {
                $this->writer->record($author->id, $author->currentTeam->id, $points);
            }
            DB::afterCommit(fn () => Cache::forget("fanfiction_count_{$story->team_id}"));

            return true;
        }, 3);
    }
}
