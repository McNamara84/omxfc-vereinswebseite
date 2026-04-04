<?php

namespace App\Livewire;

use App\Models\Review;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class HomeReviews extends Component
{
    #[Computed(cache: true, seconds: 300)]
    public function reviews(): Collection
    {
        $team = Team::membersTeam();

        if (! $team) {
            return collect();
        }

        return Review::withoutTrashed()
            ->where('team_id', $team->id)
            ->with(['book:id,roman_number,title'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Review $review) => [
                'roman_number' => $review->book->roman_number,
                'roman_title' => $review->book->title,
                'review_title' => $review->title,
                'reviewed_at' => $review->created_at,
                'excerpt' => mb_strimwidth(
                    trim(strip_tags(Str::markdown($review->content))),
                    0,
                    75,
                    '…',
                    'UTF-8'
                ),
            ]);
    }

    public function render()
    {
        return view('livewire.home-reviews');
    }
}
