<?php

namespace App\Livewire;

use App\Models\Review;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class HomeReviews extends Component
{
    private const CACHE_KEY_PREFIX = 'home_reviews.latest.v1.team.';

    private const CACHE_TTL_SECONDS = 300;

    #[Computed]
    public function reviews(): Collection
    {
        $team = Team::membersTeam();

        if (! $team) {
            return collect();
        }

        return collect(Cache::remember(
            self::cacheKeyForTeam($team->id),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn (): array => Review::withoutTrashed()
                ->where('team_id', $team->id)
                ->with(['book:id,roman_number,title'])
                ->latest()
                ->take(5)
                ->get()
                ->map(fn (Review $review) => [
                    'roman_number' => $review->book->roman_number,
                    'roman_title' => $review->book->title,
                    'review_title' => $review->title,
                    'reviewed_at_iso' => $review->created_at->toISOString(),
                    'reviewed_at_label' => $review->created_at->isoFormat('D. MMM YYYY'),
                    'excerpt' => mb_strimwidth(
                        trim(strip_tags(Str::markdown($review->content))),
                        0,
                        75,
                        '…',
                        'UTF-8'
                    ),
                ])
                ->all(),
        ));
    }

    public static function cacheKeyForTeam(int $teamId): string
    {
        return self::CACHE_KEY_PREFIX.$teamId;
    }

    public function render()
    {
        return view('livewire.home-reviews');
    }
}
