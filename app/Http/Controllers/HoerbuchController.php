<?php

namespace App\Http\Controllers;

use App\Models\AudiobookEpisode;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Rules\ValidReleaseTime;
use Carbon\Carbon;

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

        $episodes = AudiobookEpisode::all()
            ->sortBy(function ($episode) {
                return $this->parsePlannedReleaseDate($episode->planned_release_date) ?? Carbon::create(9999, 12, 31);
            })
            ->values();

        return view('hoerbuecher.index', [
            'episodes' => $episodes,
        ]);
    }

    private function parsePlannedReleaseDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        $formats = ['d.m.Y', 'm.Y', 'Y'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
            } catch (\Exception $e) {
                continue;
            }

            if ($format === 'm.Y') {
                $date->day = 1;
            } elseif ($format === 'Y') {
                $date->month = 1;
                $date->day = 1;
            }

            return $date;
        }

        return null;
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
            'roles_total',
            'roles_filled',
        ]);

        $data['notes'] = $this->sanitizeNotes($data['notes'] ?? null);

        return $data;
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
            'planned_release_date' => ['required', 'string', new ValidReleaseTime()],
            'status' => 'required|in:' . implode(',', AudiobookEpisode::STATUSES),
            'responsible_user_id' => 'nullable|exists:users,id',
            'progress' => 'required|integer|min:0|max:100',
            'roles_total' => 'required|integer|min:0',
            'roles_filled' => 'required|integer|min:0|lte:roles_total',
            'notes' => 'nullable|string',
        ]);
        AudiobookEpisode::create($this->episodeDataFromRequest($request));

        return redirect()->route('hoerbuecher.index')
            ->with('status', 'Hörbuchfolge wurde gespeichert.');
    }

    /**
     * Detailansicht einer Hörbuchfolge.
     */
    public function show(AudiobookEpisode $episode)
    {
        $this->ensureAdminOrVorstand();

        return view('hoerbuecher.show', [
            'episode' => $episode,
        ]);
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
            'planned_release_date' => ['required', 'string', new ValidReleaseTime()],
            'status' => 'required|in:' . implode(',', AudiobookEpisode::STATUSES),
            'responsible_user_id' => 'nullable|exists:users,id',
            'progress' => 'required|integer|min:0|max:100',
            'roles_total' => 'required|integer|min:0',
            'roles_filled' => 'required|integer|min:0|lte:roles_total',
            'notes' => 'nullable|string',
        ]);
        $episode->update($this->episodeDataFromRequest($request));

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
