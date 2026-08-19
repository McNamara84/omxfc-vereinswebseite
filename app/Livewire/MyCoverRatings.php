<?php

namespace App\Livewire;

use App\Enums\BookType;
use App\Models\CoverRating;
use App\Services\CoverRatings\CoverRatingAccessService;
use App\Services\CoverRatings\CoverRatingResultService;
use App\Services\CoverRatings\CoverRatingService;
use App\Services\CoverRatings\CoverSelectionService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class MyCoverRatings extends Component
{
    use WithPagination;

    #[Url(except: 'all')]
    public string $series = 'all';

    public string $statusMessage = '';

    public function mount(CoverRatingAccessService $access): void
    {
        $access->ensureMemberAccess();
        app(CoverSelectionService::class)->resolveSeries($this->series);
    }

    public function updatedSeries(string $value): void
    {
        app(CoverRatingAccessService::class)->ensureMemberAccess();
        app(CoverSelectionService::class)->resolveSeries($value);
        $this->resetPage(pageName: 'ratingsPage');
        unset($this->ratings);
    }

    public function updateRating(int $ratingId, int $value): void
    {
        $user = app(CoverRatingAccessService::class)->ensureMemberAccess();
        $rating = CoverRating::query()->where('user_id', $user->id)->findOrFail($ratingId);
        app(CoverRatingService::class)->update($user, $rating, $value);
        $this->statusMessage = 'Deine Bewertung wurde aktualisiert.';
        unset($this->ratings);
    }

    public function deleteRating(int $ratingId): void
    {
        $user = app(CoverRatingAccessService::class)->ensureMemberAccess();
        $rating = CoverRating::query()->where('user_id', $user->id)->findOrFail($ratingId);
        app(CoverRatingService::class)->delete($user, $rating);
        $this->statusMessage = 'Deine Bewertung wurde gelöscht. Das Cover kann erneut bewertet werden.';
        $this->resetPage(pageName: 'ratingsPage');
        unset($this->ratings);
    }

    #[Computed]
    public function ratings()
    {
        $user = app(CoverRatingAccessService::class)->ensureMemberAccess();

        return app(CoverRatingResultService::class)
            ->personalQuery($user, $this->series)
            ->paginate(18, pageName: 'ratingsPage');
    }

    #[Computed]
    public function seriesOptions(): array
    {
        return BookType::options(includeAll: true);
    }

    public function render()
    {
        return view('livewire.my-cover-ratings')
            ->layout('layouts.app', ['title' => 'Meine Cover-Bewertungen']);
    }
}
