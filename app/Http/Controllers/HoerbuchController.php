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
    /**
     * Übersicht aller Hörbuchfolgen.
     */
    public function index()
    {
        $episodes = AudiobookEpisode::all()->map(function ($episode) {
            $date = $this->parsePlannedReleaseDate($episode->planned_release_date);
            $episode->year = $date?->year;
            return $episode;
        })
            ->sortBy(function ($episode) {
                return $this->parsePlannedReleaseDate($episode->planned_release_date) ?? Carbon::create(9999, 12, 31);
            })
            ->values();

        $years = $episodes->pluck('year')->filter()->unique()->sort()->values();

        return view('hoerbuecher.index', [
            'episodes' => $episodes,
            'statuses' => AudiobookEpisode::STATUSES,
            'years' => $years,
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
        return view('hoerbuecher.show', [
            'episode' => $episode,
        ]);
    }

    /**
     * Formular zum Bearbeiten einer Hörbuchfolge.
     */
    public function edit(AudiobookEpisode $episode)
    {
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
        $episode->delete();

        return redirect()->route('hoerbuecher.index')
            ->with('status', 'Hörbuchfolge wurde gelöscht.');
    }
}
