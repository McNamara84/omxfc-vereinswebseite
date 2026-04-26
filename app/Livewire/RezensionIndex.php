<?php

namespace App\Livewire;

use App\Enums\BookType;
use App\Enums\Role;
use App\Models\Book;
use App\Models\User;
use App\Services\MaddraxDataService;
use App\Services\MembersTeamProvider;
use App\Services\ReviewBaxxService;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class RezensionIndex extends Component
{
    #[Url]
    public ?int $roman_number = null;

    #[Url]
    public string $title_filter = '';

    #[Url]
    public string $author = '';

    #[Url]
    public string $review_status = '';

    public function mount(): void
    {
        try {
            $role = app(UserRoleService::class)->getRole(Auth::user(), app(MembersTeamProvider::class)->getMembersTeamOrAbort());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            abort(403);
        }

        if (! $role || ! in_array($role, [Role::Mitglied, Role::Ehrenmitglied, Role::Kassenwart, Role::Vorstand, Role::Admin], true)) {
            abort(403);
        }
    }

    public function resetFilters(): void
    {
        $this->roman_number = null;
        $this->title_filter = '';
        $this->author = '';
        $this->review_status = '';
    }

    #[Computed]
    public function filtersApplied(): bool
    {
        return $this->roman_number || $this->title_filter !== '' || $this->author !== '' || $this->review_status !== '';
    }

    #[Computed]
    public function reviewRewardConfiguration(): array
    {
        return app(ReviewBaxxService::class)->getMemberConfiguration();
    }

    protected function applyFilters(Builder $query): Builder
    {
        if ($this->roman_number) {
            $query->where('roman_number', $this->roman_number);
        }

        if ($this->title_filter !== '') {
            $query->where('title', 'like', '%'.$this->title_filter.'%');
        }

        if ($this->author !== '') {
            $query->where('author', 'like', '%'.$this->author.'%');
        }

        if ($this->review_status === 'with') {
            $query->whereHas('reviews');
        } elseif ($this->review_status === 'without') {
            $query->doesntHave('reviews');
        }

        return $query;
    }

    protected function prepareBookQuery(
        Builder $query,
        User $user,
        int $teamId,
        string $direction = 'asc'
    ): Collection {
        return $query->withCount('reviews')
            ->withExists(['reviews as has_review' => function ($query) use ($user, $teamId) {
                $query->where('team_id', $teamId)
                    ->where('user_id', $user->id);
            }])
            ->orderBy('roman_number', $direction)
            ->get();
    }

    public function render()
    {
        $user = Auth::user();
        $teamId = app(MembersTeamProvider::class)->getMembersTeamOrAbort()->id;
        $maddraxDataService = app(MaddraxDataService::class);

        $novelsQuery = $this->applyFilters(Book::query()->where('type', BookType::MaddraxDieDunkleZukunftDerErde));
        $hardcoversQuery = $this->applyFilters(Book::query()->where('type', BookType::MaddraxHardcover));
        $missionMarsQuery = $this->applyFilters(Book::query()->where('type', BookType::MissionMars));
        $miniSeries2012Query = $this->applyFilters(Book::query()->where('type', BookType::ZweiTausendZwölfDasJahrDerApokalypse));
        $volkDerTiefeQuery = $this->applyFilters(Book::query()->where('type', BookType::DasVolkDerTiefe));
        $abenteurerQuery = $this->applyFilters(Book::query()->where('type', BookType::DieAbenteurer));

        $books = $this->prepareBookQuery($novelsQuery, $user, $teamId);
        $hardcovers = $this->prepareBookQuery($hardcoversQuery, $user, $teamId, 'desc');
        $missionMars = $this->prepareBookQuery($missionMarsQuery, $user, $teamId, 'desc');
        $miniSeries2012 = $this->prepareBookQuery($miniSeries2012Query, $user, $teamId, 'desc');
        $volkDerTiefe = $this->prepareBookQuery($volkDerTiefeQuery, $user, $teamId, 'desc');
        $abenteurer = $this->prepareBookQuery($abenteurerQuery, $user, $teamId, 'desc');

        $cycleMap = $maddraxDataService->getCycleMap();
        $books->each(function ($book) use ($cycleMap) {
            $book->cycle = $cycleMap[$book->roman_number] ?? 'Unbekannt';
        });

        $existingCycles = $books->pluck('cycle')->unique();

        $preferredCycleOrder = collect([
            'Weltrat', 'Amraka', 'Weltenriss', 'Parallelwelt', 'Fremdwelt',
            'Zeitsprung', 'Archivar', 'Ursprung', 'Streiter', 'Schatten',
            'Antarktis', 'Afra', 'Ausala', 'Mars', 'Wandler',
            "Daa'muren", 'Kratersee', 'Expedition', 'Meeraka', 'Euree',
        ]);

        $unlistedCycles = $existingCycles
            ->reject(fn ($cycle) => $preferredCycleOrder->contains($cycle))
            ->sort();

        $cycleOrder = $preferredCycleOrder
            ->filter(fn ($cycle) => $existingCycles->contains($cycle))
            ->concat($unlistedCycles);

        $booksByCycle = $cycleOrder
            ->mapWithKeys(function ($cycle) use ($books) {
                $cycleBooks = $books->where('cycle', $cycle)->sortByDesc('roman_number');

                if ($cycleBooks->isEmpty()) {
                    return [];
                }

                return [$cycle => $cycleBooks];
            });

        return view('livewire.rezension-index', [
            'booksByCycle' => $booksByCycle,
            'hardcovers' => $hardcovers,
            'missionMars' => $missionMars,
            'miniSeries2012' => $miniSeries2012,
            'volkDerTiefe' => $volkDerTiefe,
            'abenteurer' => $abenteurer,
        ])->layout('layouts.app', [
            'title' => 'Rezensionen – Offizieller MADDRAX Fanclub e. V.',
            'description' => 'Alle Vereinsrezensionen zu den Maddrax-Romanen im Überblick.',
        ]);
    }

    public function placeholder()
    {
        return view('components.skeleton-table', ['columns' => 4, 'rows' => 10]);
    }
}
