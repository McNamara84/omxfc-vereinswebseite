<?php

namespace App\Http\Controllers;

use App\Enums\AudiobookEpisodeStatus;
use App\Models\AudiobookEpisode;
use App\Models\AudiobookRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Http\Requests\AudiobookEpisodeRequest;
use App\Http\Requests\AudiobookPreviousSpeakerRequest;
use Illuminate\Support\Str;

class HoerbuchController extends Controller
{
    /**
     * Übersicht aller Hörbuchfolgen.
     */
    public function index()
    {
        $episodes = AudiobookEpisode::with(['roles:id,episode_id,name,user_id,speaker_name'])
            ->get()
            ->sortBy(function ($episode) {
                return $episode->planned_release_date_parsed ?? Carbon::create(9999, 12, 31);
            })
            ->values();

        $years = $episodes->pluck('release_year')->filter()->unique()->sort()->values();

        $roleNames = $episodes
            ->flatMap->roles
            ->pluck('name')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        // totalUnfilledRoles counts unique, case-insensitive role names among roles that
        // individually have neither an assigned member nor a speaker name.
        $totalUnfilledRoles = $episodes
            ->flatMap->roles
            ->map(function ($role) {
                $normalizedName = trim((string) $role->name);

                return [
                    'normalized' => Str::lower($normalizedName),
                    'hasName' => $normalizedName !== '',
                    'isAssigned' => filled($role->user_id) || filled($role->speaker_name),
                ];
            })
            ->filter(fn ($role) => $role['hasName'] && ! $role['isAssigned'])
            ->unique('normalized')
            ->count();

        // episodesWithUnassignedRoles counts episodes that contain at least one role
        // without an assigned member or speaker name, mirroring the
        // totalUnfilledRoles aggregation but on an episode basis.
        $episodesWithUnassignedRoles = $episodes
            ->filter(fn ($episode) => $episode->roles->contains(
                fn ($role) => blank($role->user_id) && blank($role->speaker_name)
            ))
            ->count();

        $nextEpisode = $episodes
            ->filter(fn ($e) => $e->planned_release_date_parsed?->isFuture())
            ->sortBy('planned_release_date_parsed')
            ->first();

        $daysUntilNextEvt = null;
        if ($nextEpisode?->planned_release_date_parsed) {
            $diff = Carbon::now()->diffInDays($nextEpisode->planned_release_date_parsed, false);
            $daysUntilNextEvt = (int) max(0, $diff);
        }

        return view('hoerbuecher.index', [
            'episodes' => $episodes,
            'statuses' => AudiobookEpisodeStatus::values(),
            'years' => $years,
            'roleNames' => $roleNames,
            'totalUnfilledRoles' => $totalUnfilledRoles,
            'episodesWithUnassignedRoles' => $episodesWithUnassignedRoles,
            'nextEpisode' => $nextEpisode,
            'daysUntilNextEvt' => $daysUntilNextEvt,
        ]);
    }

    private function sanitizeNotes(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        $notes = trim(strip_tags($notes));

        return $notes === '' ? null : $notes;
    }

    private function episodeDataFromRequest(AudiobookEpisodeRequest $request): array
    {
        $data = $request->safe()->only([
            'episode_number',
            'title',
            'author',
            'planned_release_date',
            'status',
            'responsible_user_id',
            'progress',
            'notes',
        ]);

        $data['notes'] = $this->sanitizeNotes($data['notes'] ?? null);

        return $data;
    }

    private function latestSpeakersForNames($names): array
    {
        $names = collect($names)->filter()->unique();
        if ($names->isEmpty()) {
            return [];
        }

        return AudiobookRole::useIndex('audiobook_roles_name_user_speaker_index')
            ->whereIn('name', $names)
            ->where(fn ($q) => $q->whereNotNull('user_id')->orWhereNotNull('speaker_name'))
            ->with('user')
            ->orderByDesc('id')
            ->orderBy('name')
            ->get()
            ->groupBy('name')
            ->map(fn ($r) => $r->first()->user?->name ?? $r->first()->speaker_name)
            ->toArray();
    }

    private function previousSpeakersForEpisode(AudiobookEpisode $episode): array
    {
        $episode->loadMissing('roles');

        return $this->latestSpeakersForNames($episode->roles->pluck('name'));
    }

    /**
     * Formular zum Erstellen einer neuen Hörbuchfolge.
     */
    public function create()
    {
        $users = User::orderBy('name')->get();

        return view('hoerbuecher.create', [
            'users' => $users,
            'statuses' => AudiobookEpisodeStatus::values(),
        ]);
    }

    /**
     * Speichert eine neue Hörbuchfolge.
     */
    public function store(AudiobookEpisodeRequest $request)
    {
        $episode = AudiobookEpisode::create($this->episodeDataFromRequest($request));

        $roles = $request->validated()['roles'] ?? [];
        foreach ($roles as $role) {
            AudiobookRole::create([
                'episode_id' => $episode->id,
                'name' => $role['name'],
                'description' => $role['description'] ?? null,
                'takes' => $role['takes'] ?? 0,
                'user_id' => $role['member_id'] ?? null,
                'speaker_name' => $role['member_name'] ?? null,
                'contact_email' => $role['contact_email'] ?? null,
                'speaker_pseudonym' => $role['speaker_pseudonym'] ?? null,
                'uploaded' => (bool) ($role['uploaded'] ?? false),
            ]);
        }

        $episode->update([
            'roles_total' => count($roles),
            'roles_filled' => collect($roles)->filter(fn ($r) => ($r['member_id'] ?? null) || ($r['member_name'] ?? null))->count(),
        ]);

        return redirect()->route('hoerbuecher.index')
            ->with('status', 'Hörbuchfolge wurde gespeichert.');
    }

    /**
     * Detailansicht einer Hörbuchfolge.
     */
    public function show(AudiobookEpisode $episode)
    {
        $episode->load('roles.user');

        $previous = $this->previousSpeakersForEpisode($episode);

        return view('hoerbuecher.show', [
            'episode' => $episode,
            'previousSpeakers' => $previous,
        ]);
    }

    /**
     * Formular zum Bearbeiten einer Hörbuchfolge.
     */
    public function edit(AudiobookEpisode $episode)
    {
        $users = User::orderBy('name')->get();

        $episode = $episode->load('roles');
        $previous = $this->previousSpeakersForEpisode($episode);

        return view('hoerbuecher.edit', [
            'episode' => $episode,
            'users' => $users,
            'statuses' => AudiobookEpisodeStatus::values(),
            'previousSpeakers' => $previous,
        ]);
    }

    /**
     * Aktualisiert eine bestehende Hörbuchfolge.
     */
    public function update(AudiobookEpisodeRequest $request, AudiobookEpisode $episode)
    {
        $episode->update($this->episodeDataFromRequest($request));

        $roles = $request->validated()['roles'] ?? [];
        $episode->roles()->delete();
        foreach ($roles as $role) {
            AudiobookRole::create([
                'episode_id' => $episode->id,
                'name' => $role['name'],
                'description' => $role['description'] ?? null,
                'takes' => $role['takes'] ?? 0,
                'user_id' => $role['member_id'] ?? null,
                'speaker_name' => $role['member_name'] ?? null,
                'contact_email' => $role['contact_email'] ?? null,
                'speaker_pseudonym' => $role['speaker_pseudonym'] ?? null,
                'uploaded' => (bool) ($role['uploaded'] ?? false),
            ]);
        }
        $episode->update([
            'roles_total' => count($roles),
            'roles_filled' => collect($roles)->filter(fn ($r) => ($r['member_id'] ?? null) || ($r['member_name'] ?? null))->count(),
        ]);

        return redirect()->route('hoerbuecher.index')
            ->with('status', 'Hörbuchfolge wurde aktualisiert.');
    }

    /**
     * Gibt den bisherigen Sprecher einer Rolle zurück.
     */
    public function previousSpeaker(AudiobookPreviousSpeakerRequest $request): JsonResponse
    {
        $name = $request->validated()['name'];
        $speakers = $this->latestSpeakersForNames([$name]);

        return response()->json([
            'speaker' => $speakers[$name] ?? null,
        ]);
    }

    /**
     * Aktualisiert den Upload-Status einer Rolle.
     */
    public function updateRoleUploaded(Request $request, AudiobookRole $role): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'uploaded' => ['required', 'boolean'],
        ]);

        $role->update(['uploaded' => (bool) $validated['uploaded']]);

        if ($request->wantsJson()) {
            return response()->json([
                'uploaded' => $role->uploaded,
            ]);
        }

        return back()->with('status', 'Upload-Status wurde aktualisiert.');
    }

    /**
     * Löscht eine Hörbuchfolge.
     */
    public function destroy(AudiobookEpisode $episode)
    {
        $episode->delete();

        return redirect()->route('hoerbuecher.index')
            ->with('status', 'Hörbuchfolge wurde gelöscht.');
    }
}
