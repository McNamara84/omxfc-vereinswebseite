<?php

namespace App\Livewire;

use App\Enums\AudiobookEpisodeStatus;
use App\Models\AudiobookEpisode;
use App\Models\AudiobookRole;
use App\Models\User;
use App\Rules\ValidReleaseTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class HoerbuchForm extends Component
{
    #[Locked]
    public ?int $episodeId = null;

    public string $episode_number = '';

    public string $title = '';

    public string $author = '';

    public string $planned_release_date = '';

    public string $status = '';

    public ?int $responsible_user_id = null;

    public int $progress = 0;

    public ?string $notes = null;

    public array $roles = [];

    public function mount(?AudiobookEpisode $episode = null): void
    {
        $user = Auth::user();
        if (! $user || ! ($user->hasVorstandRole() || $user->isOwnerOfTeam('AG Fanhörbücher'))) {
            abort(403);
        }

        if ($episode?->exists) {
            $this->episodeId = $episode->id;
            $episode->loadMissing('roles');

            $this->episode_number = $episode->episode_number;
            $this->title = $episode->title;
            $this->author = $episode->author;
            $this->planned_release_date = $episode->planned_release_date;
            $this->status = $episode->status->value;
            $this->responsible_user_id = $episode->responsible_user_id;
            $this->progress = $episode->progress;
            $this->notes = $episode->notes;

            $previousSpeakers = $this->previousSpeakersForEpisode($episode);

            $this->roles = $episode->roles->map(fn ($r) => [
                'name' => $r->name ?? '',
                'description' => $r->description ?? '',
                'takes' => $r->takes ?? 0,
                'member_id' => $r->user_id ? (string) $r->user_id : '',
                'member_name' => $r->user?->name ?? $r->speaker_name ?? '',
                'contact_email' => $r->contact_email ?? '',
                'speaker_pseudonym' => $r->speaker_pseudonym ?? '',
                'uploaded' => (bool) $r->uploaded,
                'previousSpeaker' => isset($previousSpeakers[$r->name])
                    ? 'Bisheriger Sprecher: '.$previousSpeakers[$r->name]
                    : '',
            ])->toArray();
        }
    }

    #[Computed]
    public function users()
    {
        return User::orderBy('name')->get();
    }

    #[Computed]
    public function statuses(): array
    {
        return AudiobookEpisodeStatus::values();
    }

    #[Computed]
    public function isEditing(): bool
    {
        return $this->episodeId !== null;
    }

    public function addRole(): void
    {
        $this->roles[] = [
            'name' => '',
            'description' => '',
            'takes' => 0,
            'member_id' => '',
            'member_name' => '',
            'contact_email' => '',
            'speaker_pseudonym' => '',
            'uploaded' => false,
            'previousSpeaker' => '',
        ];
    }

    public function removeRole(int $index): void
    {
        unset($this->roles[$index]);
        $this->roles = array_values($this->roles);
    }

    public function save(): void
    {
        $rules = [
            'episode_number' => [
                'required',
                'string',
                'max:10',
                Rule::unique('audiobook_episodes', 'episode_number')->ignore($this->episodeId),
            ],
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'planned_release_date' => ['required', 'string', new ValidReleaseTime],
            'status' => 'required|in:'.implode(',', AudiobookEpisodeStatus::values()),
            'responsible_user_id' => 'nullable|exists:users,id',
            'progress' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string',
            'roles' => 'array',
            'roles.*.name' => 'required|string|max:255',
            'roles.*.description' => 'nullable|string|max:1000',
            'roles.*.takes' => 'required|integer|min:0',
            'roles.*.member_id' => 'nullable|exists:users,id',
            'roles.*.member_name' => 'nullable|string|max:255',
            'roles.*.contact_email' => 'nullable|email:rfc|max:255',
            'roles.*.speaker_pseudonym' => 'nullable|string|max:255',
            'roles.*.uploaded' => 'nullable|boolean',
        ];

        // Leere Strings aus Selects/Inputs in nullable-Feldern explizit auf null normalisieren,
        // damit die exists-Validierung nicht gegen "" läuft (Livewire setzt anders als HTTP-Requests
        // ConvertEmptyStringsToNull nicht automatisch).
        if ($this->responsible_user_id === '') {
            $this->responsible_user_id = null;
        }
        foreach ($this->roles as $i => $role) {
            if (($role['member_id'] ?? null) === '') {
                $this->roles[$i]['member_id'] = null;
            }
        }

        $validated = $this->validate($rules);

        $notes = $this->sanitizeNotes($validated['notes'] ?? null);

        $episodeData = [
            'episode_number' => $validated['episode_number'],
            'title' => $validated['title'],
            'author' => $validated['author'],
            'planned_release_date' => $validated['planned_release_date'],
            'status' => $validated['status'],
            'responsible_user_id' => $validated['responsible_user_id'],
            'progress' => $validated['progress'],
            'notes' => $notes,
        ];

        $rolesData = $validated['roles'] ?? [];

        if ($this->isEditing) {
            $episode = AudiobookEpisode::findOrFail($this->episodeId);
            $episode->update($episodeData);
            $episode->roles()->delete();
        } else {
            $episode = AudiobookEpisode::create($episodeData);
        }

        foreach ($rolesData as $role) {
            AudiobookRole::create([
                'episode_id' => $episode->id,
                'name' => $role['name'],
                'description' => $role['description'] ?? null,
                'takes' => $role['takes'] ?? 0,
                'user_id' => ! empty($role['member_id']) ? $role['member_id'] : null,
                'speaker_name' => $role['member_name'] ?? null,
                'contact_email' => $role['contact_email'] ?? null,
                'speaker_pseudonym' => $role['speaker_pseudonym'] ?? null,
                'uploaded' => (bool) ($role['uploaded'] ?? false),
            ]);
        }

        $episode->update([
            'roles_total' => count($rolesData),
            'roles_filled' => collect($rolesData)->filter(fn ($r) => ! empty($r['member_id']) || ! empty($r['member_name']))->count(),
        ]);

        $message = $this->isEditing
            ? 'Hörbuchfolge wurde aktualisiert.'
            : 'Hörbuchfolge wurde gespeichert.';

        session()->flash('toast', ['type' => 'success', 'title' => $message]);
        $this->redirect(route('hoerbuecher.index'), navigate: true);
    }

    private function sanitizeNotes(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        $notes = trim(strip_tags($notes));

        return $notes === '' ? null : $notes;
    }

    private function previousSpeakersForEpisode(AudiobookEpisode $episode): array
    {
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

    public function placeholder()
    {
        return view('components.skeleton-form', ['fields' => 6]);
    }

    public function render()
    {
        return view('livewire.hoerbuch-form')
            ->layout('layouts.app', ['title' => $this->isEditing ? 'Hörbuchfolge bearbeiten' : 'Neue Hörbuchfolge']);
    }
}
