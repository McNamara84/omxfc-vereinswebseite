<?php

namespace App\Livewire;

use App\Enums\AudiobookEpisodeStatus;
use App\Models\AudiobookEpisode;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class HoerbuchIndex extends Component
{
    #[Computed]
    public function episodes()
    {
        return AudiobookEpisode::with(['roles:id,episode_id,name,user_id,speaker_name'])
            ->get()
            ->sortBy(fn ($episode) => $episode->planned_release_date_parsed ?? Carbon::create(9999, 12, 31))
            ->values();
    }

    #[Computed]
    public function statuses(): array
    {
        return AudiobookEpisodeStatus::values();
    }

    #[Computed]
    public function years()
    {
        return $this->episodes->pluck('release_year')->filter()->unique()->sort()->values();
    }

    #[Computed]
    public function roleNames()
    {
        return $this->episodes
            ->flatMap->roles
            ->pluck('name')
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    #[Computed]
    public function totalUnfilledRoles(): int
    {
        return $this->episodes
            ->flatMap->roles
            ->map(fn ($role) => [
                'normalized' => Str::lower(trim((string) $role->name)),
                'hasName' => trim((string) $role->name) !== '',
                'isAssigned' => filled($role->user_id) || filled($role->speaker_name),
            ])
            ->filter(fn ($role) => $role['hasName'] && ! $role['isAssigned'])
            ->unique('normalized')
            ->count();
    }

    #[Computed]
    public function episodesWithUnassignedRoles(): int
    {
        return $this->episodes
            ->filter(fn ($episode) => $episode->roles->contains(
                fn ($role) => blank($role->user_id) && blank($role->speaker_name)
            ))
            ->count();
    }

    #[Computed]
    public function nextEpisode()
    {
        return $this->episodes
            ->filter(fn ($e) => $e->planned_release_date_parsed?->isFuture())
            ->sortBy('planned_release_date_parsed')
            ->first();
    }

    #[Computed]
    public function daysUntilNextEvt(): ?int
    {
        if (! $this->nextEpisode?->planned_release_date_parsed) {
            return null;
        }

        $diff = Carbon::now()->diffInDays($this->nextEpisode->planned_release_date_parsed, false);

        return (int) max(0, $diff);
    }

    public function placeholder()
    {
        return view('components.skeleton-table', ['columns' => 5, 'rows' => 8]);
    }

    public function render()
    {
        return view('livewire.hoerbuch-index')
            ->layout('layouts.app', ['title' => 'Hörbuchfolgen']);
    }
}
