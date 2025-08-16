<?php

namespace App\Http\Controllers;

use App\Models\AudiobookEpisode;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HoerbuchController extends Controller
{
    private function ensureAdminOrVorstand(): void
    {
        $user = Auth::user();
        $memberTeam = Team::where('name', 'Mitglieder')->first();
        $membership = $memberTeam?->users()->where('user_id', $user->id)->first();
        $userRole = $membership ? $membership->membership->role : null;

        if (!in_array($userRole, ['Vorstand', 'Admin'], true)) {
            abort(403);
        }
    }
    /**
     * Übersicht aller Hörbuchfolgen.
     */
    public function index()
    {
        $this->ensureAdminOrVorstand();

        $episodes = AudiobookEpisode::orderBy('episode_number')->get();

        return view('hoerbuecher.index', [
            'episodes' => $episodes,
        ]);
    }
    /**
     * Formular zum Erstellen einer neuen Hörbuchfolge.
     */
    public function create()
    {
        $this->ensureAdminOrVorstand();

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
        $this->ensureAdminOrVorstand();

        $request->validate([
            'episode_number' => 'required|string|max:10|unique:audiobook_episodes,episode_number',
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'planned_release_date' => 'required|date',
            'status' => 'required|in:' . implode(',', AudiobookEpisode::STATUSES),
            'responsible_user_id' => 'nullable|exists:users,id',
            'progress' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        AudiobookEpisode::create($request->only([
            'episode_number',
            'title',
            'author',
            'planned_release_date',
            'status',
            'responsible_user_id',
            'progress',
            'notes',
        ]));

        return redirect()->route('hoerbuecher.create')
            ->with('status', 'Hörbuchfolge wurde gespeichert.');
    }

    /**
     * Formular zum Bearbeiten einer Hörbuchfolge.
     */
    public function edit(AudiobookEpisode $episode)
    {
        $this->ensureAdminOrVorstand();

        $users = User::orderBy('name')->get();

        return view('hoerbuecher.edit', [
            'episode' => $episode,
            'users' => $users,
            'statuses' => AudiobookEpisode::STATUSES,
        ]);
    }

    /**
     * Aktualisiert eine bestehende Hörbuchfolge.
     */
    public function update(Request $request, AudiobookEpisode $episode)
    {
        $this->ensureAdminOrVorstand();

        $request->validate([
            'episode_number' => [
                'required',
                'string',
                'max:10',
                Rule::unique('audiobook_episodes', 'episode_number')->ignore($episode->id),
            ],
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'planned_release_date' => 'required|date',
            'status' => 'required|in:' . implode(',', AudiobookEpisode::STATUSES),
            'responsible_user_id' => 'nullable|exists:users,id',
            'progress' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $episode->update($request->only([
            'episode_number',
            'title',
            'author',
            'planned_release_date',
            'status',
            'responsible_user_id',
            'progress',
            'notes',
        ]));

        return redirect()->route('hoerbuecher.index')
            ->with('status', 'Hörbuchfolge wurde aktualisiert.');
    }

    /**
     * Löscht eine Hörbuchfolge.
     */
    public function destroy(AudiobookEpisode $episode)
    {
        $this->ensureAdminOrVorstand();

        $episode->delete();

        return redirect()->route('hoerbuecher.index')
            ->with('status', 'Hörbuchfolge wurde gelöscht.');
    }
}
