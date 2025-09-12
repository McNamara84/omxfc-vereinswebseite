<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Actions\Jetstream\AddTeamMember;

class ArbeitsgruppenController extends Controller
{
    /**
     * Base query for all AG listings.
     *
     * In this application an AG (Arbeitsgruppe) is any team that is not a
     * personal team and is different from the global "Mitglieder" team. This
     * helper centralizes that filtering so all listings operate on the same
     * definition.
     */
    private function agQuery(): Builder
    {
        return Team::where('personal_team', false)
            ->where('name', '!=', 'Mitglieder')
            ->orderBy('name');
    }

    /**
     * Display a listing of the AGs.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = $this->agQuery();

        if (!$user->hasRole('Admin')) {
            $query = $query->where('user_id', $user->id);

            if (!(clone $query)->exists()) {
                abort(403);
            }
        }

        $ags = $query->get();

        return view('arbeitsgruppen.index', [
            'ags' => $ags,
        ]);
    }

    /**
     * Display a listing of the AGs for leaders only.
     */
    public function leaderIndex(Request $request)
    {
        $user = $request->user();

        $query = $this->agQuery()->where('user_id', $user->id);

        if (!(clone $query)->exists()) {
            abort(403);
        }

        return view('arbeitsgruppen.index', [
            'ags' => $query->get(),
        ]);
    }

    /**
     * Display a listing of the AGs for the public page.
     */
    public function publicIndex()
    {
        $ags = $this->agQuery()->get();

        return view('pages.arbeitsgruppen', [
            'ags' => $ags,
        ]);
    }

    /**
     * Display form to create a new AG (team).
     */
    public function create(Request $request)
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $users = User::orderBy('name')->get();

        return view('arbeitsgruppen.create', [
            'users' => $users,
        ]);
    }

    /**
     * Store a newly created AG (team).
     */
    public function store(Request $request)
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'leader_id' => 'required|exists:users,id',
            'description' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'meeting_schedule' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
        ]);

        $logoPath = $request->file('logo')?->store('ag-logos', 'public');

        $team = Team::create([
            'user_id' => $validated['leader_id'],
            'name' => $validated['name'],
            'personal_team' => false,
            'description' => $validated['description'] ?? null,
            'email' => $validated['email'] ?? null,
            'meeting_schedule' => $validated['meeting_schedule'] ?? null,
            'logo_path' => $logoPath,
        ]);

        // Attach leader to team with existing role if available
        $memberTeam = Team::membersTeam();
        $membership = $memberTeam
            ? DB::table('team_user')
                ->where('team_id', $memberTeam->id)
                ->where('user_id', $validated['leader_id'])
                ->first()
            : null;
        $leaderRole = $membership?->role;

        $team->users()->attach($validated['leader_id'], ['role' => $leaderRole]);

        return redirect()->route('arbeitsgruppen.index')
            ->with('status', 'Arbeitsgruppe wurde erstellt.');
    }

    /**
     * Show the form for editing the specified AG.
     */
    public function edit(Request $request, Team $team)
    {
        $user = $request->user();
        if (!$user->hasRole('Admin') && $team->user_id !== $user->id) {
            abort(403);
        }

        $team->load('users');

        $users = User::orderBy('name')->get();

        $memberTeam = Team::membersTeam();
        $availableMembers = $memberTeam
            ? $memberTeam->users()
                ->whereNotIn('users.id', $team->users->pluck('id'))
                ->orderBy('name')
                ->get()
            : collect();

        return view('arbeitsgruppen.edit', [
            'team' => $team,
            'users' => $users,
            'availableMembers' => $availableMembers,
        ]);
    }

    /**
     * Update the specified AG.
     */
    public function update(Request $request, Team $team)
    {
        $user = $request->user();
        if (!$user->hasRole('Admin') && $team->user_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'leader_id' => 'required|exists:users,id',
            'description' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'meeting_schedule' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
        ]);

        if (!$user->hasRole('Admin')) {
            $validated['leader_id'] = $team->user_id;
            $validated['name'] = $team->name;
        }

        $logoPath = $request->file('logo')?->store('ag-logos', 'public') ?? $team->logo_path;

        $team->update([
            'user_id' => $validated['leader_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'email' => $validated['email'] ?? null,
            'meeting_schedule' => $validated['meeting_schedule'] ?? null,
            'logo_path' => $logoPath,
        ]);

        if ($user->hasRole('Admin') && $team->wasChanged('user_id')) {
            $memberTeam = Team::membersTeam();
            $membership = $memberTeam
                ? DB::table('team_user')
                    ->where('team_id', $memberTeam->id)
                    ->where('user_id', $validated['leader_id'])
                    ->first()
                : null;
            $leaderRole = $membership?->role;
            $team->users()->syncWithoutDetaching([$validated['leader_id'] => ['role' => $leaderRole]]);
        }

        return redirect()->route('arbeitsgruppen.index')
            ->with('status', 'Arbeitsgruppe wurde aktualisiert.');
    }

    /**
     * Add a member to the specified AG.
     */
    public function addMember(Request $request, Team $team, AddTeamMember $adder)
    {
        $user = $request->user();
        if (!$user->hasRole('Admin') && $team->user_id !== $user->id) {
            abort(403);
        }

        Validator::make($request->all(), [
            'user_id' => ['required', 'exists:users,id'],
        ])->validateWithBag('addTeamMember');

        if ($team->users()->count() >= 5) {
            throw ValidationException::withMessages([
                'user_id' => 'Eine AG kann maximal 5 Mitglieder haben.',
            ])->errorBag('addTeamMember');
        }

        $member = User::findOrFail($request->input('user_id'));
        $adder->add($user, $team, $member->email, 'Mitwirkender');

        return redirect()->route('arbeitsgruppen.edit', $team)
            ->with('status', 'Mitglied hinzugefügt.');
    }
}
