<?php

namespace App\Http\Controllers;

use App\Models\AudiobookEpisode;
use App\Models\AudiobookRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Rules\ValidReleaseTime;
use Carbon\Carbon;

class HoerbuchController extends Controller
{
    /**
     * Übersicht aller Hörbuchfolgen.
     */
    public function index()
    {
        $episodes = AudiobookEpisode::all()
            ->sortBy(function ($episode) {
                return $episode->planned_release_date_parsed ?? Carbon::create(9999, 12, 31);
            })
            ->values();

        $years = $episodes->pluck('release_year')->filter()->unique()->sort()->values();

        $totalUnfilledRoles = $episodes
            ->sum(fn ($e) => max($e->roles_total - $e->roles_filled, 0));

        $openRolesEpisodes = $episodes
            ->filter(fn ($e) => $e->roles_total > $e->roles_filled)
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
            'statuses' => AudiobookEpisode::STATUSES,
            'years' => $years,
            'totalUnfilledRoles' => $totalUnfilledRoles,
            'openRolesEpisodes' => $openRolesEpisodes,
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

    private function episodeDataFromRequest(Request $request): array
    {
        $data = $request->only([
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

    private function previousSpeakersForEpisode(AudiobookEpisode $episode): array
    {
        $episode->loadMissing('roles');

        $names = $episode->roles->pluck('name')->filter()->unique();
        if ($names->isEmpty()) {
            return [];
        }

        return AudiobookRole::whereIn('name', $names)
            ->where(fn ($q) => $q->whereNotNull('user_id')->orWhereNotNull('speaker_name'))
            ->with('user')
            ->orderBy('id')
            ->get()
            ->groupBy('name')
            ->map(fn ($r) => $r->last()->user?->name ?? $r->last()->speaker_name)
            ->toArray();
    }
    /**
     * Formular zum Erstellen einer neuen Hörbuchfolge.
     */
    public function create()
    {
        $users = User::orderBy('name')->get();

        return view('hoerbuecher.create', [
            'users' => $users,
            'statuses' => AudiobookEpisode::STATUSES,
        ]);
    }

    /**
     * Speichert eine neue Hörbuchfolge.
     */
    public function store(Request $request)
    {
        $request->validate([
            'episode_number' => 'required|string|max:10|unique:audiobook_episodes,episode_number',
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'planned_release_date' => ['required', 'string', new ValidReleaseTime()],
            'status' => 'required|in:' . implode(',', AudiobookEpisode::STATUSES),
            'responsible_user_id' => 'nullable|exists:users,id',
            'progress' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string',
            'roles' => 'array',
            'roles.*.name' => 'required|string|max:255',
            'roles.*.description' => 'nullable|string|max:1000',
            'roles.*.takes' => 'required|integer|min:0',
            'roles.*.member_id' => 'nullable|exists:users,id',
            'roles.*.member_name' => 'nullable|string|max:255',
        ]);
        $episode = AudiobookEpisode::create($this->episodeDataFromRequest($request));

        $roles = $request->input('roles', []);
        foreach ($roles as $role) {
            AudiobookRole::create([
                'episode_id' => $episode->id,
                'name' => $role['name'],
                'description' => $role['description'] ?? null,
                'takes' => $role['takes'] ?? 0,
                'user_id' => $role['member_id'] ?? null,
                'speaker_name' => $role['member_name'] ?? null,
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
            'statuses' => AudiobookEpisode::STATUSES,
            'previousSpeakers' => $previous,
        ]);
    }

    /**
     * Aktualisiert eine bestehende Hörbuchfolge.
     */
    public function update(Request $request, AudiobookEpisode $episode)
    {
        $request->validate([
            'episode_number' => [
                'required',
                'string',
                'max:10',
                Rule::unique('audiobook_episodes', 'episode_number')->ignore($episode->id),
            ],
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'planned_release_date' => ['required', 'string', new ValidReleaseTime()],
            'status' => 'required|in:' . implode(',', AudiobookEpisode::STATUSES),
            'responsible_user_id' => 'nullable|exists:users,id',
            'progress' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string',
            'roles' => 'array',
            'roles.*.name' => 'required|string|max:255',
            'roles.*.description' => 'nullable|string|max:1000',
            'roles.*.takes' => 'required|integer|min:0',
            'roles.*.member_id' => 'nullable|exists:users,id',
            'roles.*.member_name' => 'nullable|string|max:255',
        ]);
        $episode->update($this->episodeDataFromRequest($request));

        $roles = $request->input('roles', []);
        $episode->roles()->delete();
        foreach ($roles as $role) {
            AudiobookRole::create([
                'episode_id' => $episode->id,
                'name' => $role['name'],
                'description' => $role['description'] ?? null,
                'takes' => $role['takes'] ?? 0,
                'user_id' => $role['member_id'] ?? null,
                'speaker_name' => $role['member_name'] ?? null,
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
    public function previousSpeaker(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $role = AudiobookRole::where('name', $data['name'])
            ->where(fn ($q) => $q->whereNotNull('user_id')->orWhereNotNull('speaker_name'))
            ->with('user')
            ->latest('id')
            ->first();

        return response()->json([
            'speaker' => $role?->user?->name ?? $role?->speaker_name,
        ]);
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
