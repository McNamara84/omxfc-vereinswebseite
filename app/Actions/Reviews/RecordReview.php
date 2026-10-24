<?php

namespace App\Actions\Reviews;

use App\Models\Activity;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewBaxxService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class RecordReview
{
    public function __construct(private readonly ReviewBaxxService $baxx) {}

    /** Live callers authorize the submission; historical imports may contain multiple reviews per book. */
    public function execute(User $user, int $teamId, int $bookId, array $data, ?CarbonInterface $occurredAt = null): Review
    {
        return DB::transaction(function () use ($user, $teamId, $bookId, $data, $occurredAt): Review {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($occurredAt === null) {
                abort_if(Review::query()->where('user_id', $user->id)->where('team_id', $teamId)->where('book_id', $bookId)->exists(), 403);
            }
            $review = new Review([
                'team_id' => $teamId, 'user_id' => $user->id, 'book_id' => $bookId,
                'title' => $data['title'], 'content' => $data['content'],
            ]);
            if ($occurredAt !== null) {
                $review->created_at = $occurredAt;
                $review->updated_at = $occurredAt;
            }
            $review->save();
            $count = Review::query()->where('user_id', $user->id)->where('team_id', $teamId)->count();
            $this->baxx->awardPointsForReview($user, $count, $teamId, $occurredAt);
            if ($occurredAt === null) {
                Activity::create(['user_id' => $user->id, 'subject_type' => Review::class, 'subject_id' => $review->id]);
            }

            return $review;
        }, 3);
    }
}
