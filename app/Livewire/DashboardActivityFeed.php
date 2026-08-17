<?php

namespace App\Livewire;

use App\Models\Activity;
use App\Models\User;
use App\Services\Dashboard\DashboardActivityPresenter;
use App\Services\Dashboard\DashboardActivityQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DashboardActivityFeed extends Component
{
    /** @var array<int, int> */
    public array $activityIds = [];

    public string $filter = DashboardActivityQuery::DEFAULT_FILTER;

    public ?string $nextCursor = null;

    public bool $hasMore = false;

    public function mount(): void
    {
        $this->resetFeed();
    }

    public function selectFilter(string $filter): void
    {
        $this->filter = app(DashboardActivityQuery::class)->validFilter($filter);
        $this->resetFeed();
        $this->dispatch('dashboard-feed-updated');
    }

    public function loadMore(): void
    {
        if (! $this->hasMore) {
            return;
        }

        $this->loadPage($this->nextCursor);
        unset($this->activities);
        $this->dispatch('dashboard-feed-updated');
    }

    /**
     * @return Collection<int, Activity>
     */
    #[Computed]
    public function activities(): Collection
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $activities = app(DashboardActivityQuery::class)->findMany($this->activityIds);

        return app(DashboardActivityPresenter::class)->prepare($activities, $user);
    }

    public function render()
    {
        return view('livewire.dashboard-activity-feed', [
            'filters' => app(DashboardActivityQuery::class)->filters(),
        ]);
    }

    private function resetFeed(): void
    {
        $this->activityIds = [];
        $this->nextCursor = null;
        $this->hasMore = false;
        unset($this->activities);
        $this->loadPage();
    }

    private function loadPage(?string $cursor = null): void
    {
        $page = app(DashboardActivityQuery::class)->page($this->filter, $cursor);
        $newIds = $page['activities']->modelKeys();
        $this->activityIds = array_values(array_unique([...$this->activityIds, ...$newIds]));
        $this->nextCursor = $page['nextCursor'];
        $this->hasMore = $page['hasMore'];
    }
}
