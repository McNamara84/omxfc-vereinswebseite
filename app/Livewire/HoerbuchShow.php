<?php

namespace App\Livewire;

use App\Models\AudiobookEpisode;
use App\Models\AudiobookRole;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class HoerbuchShow extends Component
{
    #[Locked]
    public int $episodeId;

    public bool $confirmingDelete = false;

    public function mount(AudiobookEpisode $episode): void
    {
        $this->episodeId = $episode->id;
    }

    #[Computed]
    public function episode(): AudiobookEpisode
    {
        return AudiobookEpisode::with('roles.user')->findOrFail($this->episodeId);
    }

    #[Computed]
    public function previousSpeakers(): array
    {
        $episode = $this->episode;
        $episode->loadMissing('roles');

        $names = $episode->roles->pluck('name')->filter()->unique();
        if ($names->isEmpty()) {
            return [];
        }

        return AudiobookRole::useIndex('audiobook_roles_name_user_speaker_index')
            ->whereIn('name', $names)
            ->where(fn ($q) => $q->whereNotNull('user_id')->orWhereNotNull('speaker_name'))
            ->where('episode_id', '!=', $episode->id)
            ->with('user')
            ->orderByDesc('id')
            ->orderBy('name')
            ->get()
            ->groupBy('name')
            ->map(fn ($r) => $r->first()->user?->name ?? $r->first()->speaker_name)
            ->toArray();
    }

    #[Computed]
    public function canManage(): bool
    {
        $user = Auth::user();

        return $user && ($user->hasVorstandRole() || $user->isOwnerOfTeam('AG Fanhörbücher'));
    }

    public function deleteEpisode(): void
    {
        if (! $this->canManage) {
            abort(403);
        }

        $this->episode->delete();
        $this->confirmingDelete = false;

        session()->flash('toast', ['type' => 'success', 'title' => 'Hörbuchfolge wurde gelöscht.']);
        $this->redirect(route('hoerbuecher.index'), navigate: true);
    }

    public function placeholder()
    {
        return view('components.skeleton-detail', ['sections' => 3]);
    }

    public function render()
    {
        return view('livewire.hoerbuch-show')
            ->layout('layouts.app', ['title' => $this->episode->title]);
    }
}
