<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArbeitsgruppenController extends Controller
{
    /**
     * Display a listing of the AGs.
     */
    public function index()
    {
        $ags = Team::where('personal_team', false)
            ->where('name', '!=', 'Mitglieder')
            ->orderBy('name')
            ->get();

        return view('arbeitsgruppen.index', [
            'ags' => $ags,
        ]);
    }

    /**
     * Display a listing of the AGs for the public page.
     */
    public function publicIndex()
    {
        $ags = Team::where('personal_team', false)
            ->where('name', '!=', 'Mitglieder')
            ->orderBy('name')
            ->get();

        return view('pages.arbeitsgruppen', [
            'ags' => $ags,
        ]);
    }

    /**
     * Display form to create a new AG (team).
     */
    public function create()
    {
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
        $memberTeam = Team::where('name', 'Mitglieder')->first();
        $membership = $memberTeam
            ? DB::table('team_user')
                ->where('team_id', $memberTeam->id)
                ->where('user_id', $validated['leader_id'])
                ->first()
            : null;
        $leaderRole = $membership?->role;

        $team->users()->attach($validated['leader_id'], ['role' => $leaderRole]);

        return redirect()->route('dashboard')
            ->with('status', 'Arbeitsgruppe wurde erstellt.');
    }
}
