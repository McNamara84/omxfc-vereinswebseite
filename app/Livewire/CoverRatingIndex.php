<?php

namespace App\Livewire;

use App\Enums\BookType;
use App\Models\BookCover;
use App\Models\CoverRating;
use App\Models\User;
use App\Services\CoverRatings\CoverRatingAccessService;
use App\Services\CoverRatings\CoverRatingBaxxService;
use App\Services\CoverRatings\CoverRatingService;
use App\Services\CoverRatings\CoverSelectionService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class CoverRatingIndex extends Component
{
    #[Url(except: 'all')]
    public string $series = 'all';

    #[Locked]
    public ?int $currentCoverId = null;

    /** @var list<int> */
    #[Locked]
    public array $skippedCoverIds = [];

    #[Locked]
    public ?string $lastSeriesKey = null;

    #[Locked]
    public ?int $lastRatingId = null;

    #[Locked]
    public string $statusMessage = '';

    #[Locked]
    public int $awardedBaxx = 0;

    public function mount(CoverRatingAccessService $access): void
    {
        $user = $access->ensureMemberAccess();
        app(CoverSelectionService::class)->resolveSeries($this->series);
        $this->loadNext($user);
    }

    public function updatedSeries(string $value): void
    {
        $user = $this->member();
        app(CoverSelectionService::class)->resolveSeries($value);
        $this->skippedCoverIds = [];
        $this->lastSeriesKey = null;
        $this->statusMessage = '';
        $this->awardedBaxx = 0;
        $this->loadNext($user);
    }

    public function rate(int $value): void
    {
        $user = $this->member();

        if (! $this->currentCoverId) {
            throw ValidationException::withMessages([
                'rating' => 'Es ist aktuell kein Cover zur Bewertung ausgewählt.',
            ]);
        }

        $cover = BookCover::query()->with('book')->findOrFail($this->currentCoverId);
        $result = app(CoverRatingService::class)->rate($user, $cover->id, $value);

        $this->lastRatingId = $result['rating']->id;
        $this->lastSeriesKey = $cover->book->type->key();
        $this->awardedBaxx = $result['awarded_baxx'];
        $this->statusMessage = sprintf(
            '%s #%d wurde mit %d %s bewertet.',
            $cover->book->type->label(),
            $cover->book->roman_number,
            $value,
            $value === 1 ? 'Brina' : 'Brinas',
        );
        $this->loadNext($user);
    }

    public function skip(): void
    {
        $user = $this->member();

        if ($this->currentCoverId) {
            $this->skippedCoverIds[] = $this->currentCoverId;
            $this->skippedCoverIds = array_values(array_unique(array_map(
                'intval',
                array_slice($this->skippedCoverIds, -2000),
            )));
        }

        $this->statusMessage = 'Das Cover wurde für diese Sitzung zurückgestellt.';
        $this->awardedBaxx = 0;
        $this->loadNext($user);
    }

    public function undoLast(): void
    {
        $user = $this->member();

        if (! $this->lastRatingId) {
            return;
        }

        $rating = CoverRating::query()
            ->where('user_id', $user->id)
            ->findOrFail($this->lastRatingId);
        app(CoverRatingService::class)->delete($user, $rating);
        $this->lastRatingId = null;
        $this->awardedBaxx = 0;
        $this->statusMessage = 'Die letzte Bewertung wurde rückgängig gemacht.';

        if (! $this->currentCoverId) {
            $this->loadNext($user);
        } else {
            $this->forgetComputed();
        }
    }

    #[Computed]
    public function cover(): ?BookCover
    {
        if (! $this->currentCoverId) {
            return null;
        }

        return BookCover::query()->with('book')->find($this->currentCoverId);
    }

    #[Computed]
    public function progress(): array
    {
        return app(CoverSelectionService::class)->progress($this->member(), $this->series);
    }

    #[Computed]
    public function globalProgress(): array
    {
        return app(CoverSelectionService::class)->progress($this->member());
    }

    #[Computed]
    public function rewardProgress(): array
    {
        return app(CoverRatingBaxxService::class)->progress($this->member());
    }

    #[Computed]
    public function seriesOptions(): array
    {
        return BookType::options(includeAll: true);
    }

    public function render()
    {
        return view('livewire.cover-rating-index')
            ->layout('layouts.app', [
                'title' => 'Cover-Bewertungen – Offizieller MADDRAX Fanclub e. V.',
                'description' => 'Bewerte die Cover der Maddrax-Serien mit 1 bis 5 Brinas.',
            ]);
    }

    private function loadNext(User $user): void
    {
        $cover = app(CoverSelectionService::class)->next(
            $user,
            $this->series,
            $this->skippedCoverIds,
            $this->lastSeriesKey,
        );
        $this->currentCoverId = $cover?->id;
        $this->forgetComputed();
        $this->dispatch('cover-rating-advanced');
    }

    private function member(): User
    {
        return app(CoverRatingAccessService::class)->ensureMemberAccess();
    }

    private function forgetComputed(): void
    {
        unset($this->cover, $this->progress, $this->globalProgress, $this->rewardProgress);
    }
}
