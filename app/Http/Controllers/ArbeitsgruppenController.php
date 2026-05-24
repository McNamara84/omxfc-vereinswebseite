<?php

namespace App\Http\Controllers;

use App\Actions\Jetstream\AddTeamMember;
use App\Enums\Role;
use App\Http\Requests\ArbeitsgruppeRequest;
use App\Mail\ArbeitsgruppenKontaktNachricht;
use App\Models\Team;
use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ArbeitsgruppenController extends Controller
{
    public function __construct(private UserRoleService $userRoleService) {}

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

    private function resolvePublicAgOrAbort(Team $team): Team
    {
        $ag = $this->agQuery()
            ->whereKey($team->getKey())
            ->first();

        abort_if(! $ag || blank($ag->email), 404);

        return $ag;
    }

    /**
     * Display a listing of the AGs.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = $this->agQuery();

        if (! $user->hasRole(Role::Admin)) {
            $query = $query->where('user_id', $user->id);

            if (! (clone $query)->exists()) {
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

        if (! (clone $query)->exists()) {
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
     * Show the public AG contact form without exposing the target address in markup.
     */
    public function publicContact(Team $team)
    {
        $ag = $this->resolvePublicAgOrAbort($team);

        return view('pages.arbeitsgruppen-kontakt', [
            'ag' => $ag,
        ]);
    }

    /**
     * Send a public contact request to an AG without revealing the mail address.
     */
    public function sendPublicContact(Request $request, Team $team)
    {
        $ag = $this->resolvePublicAgOrAbort($team);

        $spamErrorMessage = 'Die Nachricht konnte nicht verarbeitet werden. Bitte versuche es erneut.';

        if (filled($request->input('website'))) {
            return back()
                ->withInput($request->except('website'))
                ->withErrors(['error' => $spamErrorMessage]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ], [
            'name.required' => 'Bitte gib deinen Namen an.',
            'email.required' => 'Bitte gib deine E-Mail-Adresse an.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
            'message.required' => 'Bitte gib eine Nachricht ein.',
            'message.min' => 'Die Nachricht sollte mindestens 10 Zeichen lang sein.',
        ]);

        Mail::to($ag->email)->queue(
            (new ArbeitsgruppenKontaktNachricht(
                team: $ag,
                absenderName: $validated['name'],
                absenderEmail: $validated['email'],
                nachricht: $validated['message'],
            ))->afterCommit(),
        );

        return redirect()->route('arbeitsgruppen.kontakt', $ag)
            ->with('status', 'Deine Nachricht wurde an die Arbeitsgruppe weitergeleitet.');
    }

    /**
     * Display form to create a new AG (team).
     */
    public function create(Request $request)
    {
        abort_unless($request->user()->hasRole(Role::Admin), 403);

        $users = User::orderBy('name')->get();

        return view('arbeitsgruppen.create', [
            'users' => $users,
        ]);
    }

    /**
     * Store a newly created AG (team).
     */
    public function store(ArbeitsgruppeRequest $request)
    {
        $validated = $request->validated();

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
        $leader = User::find($validated['leader_id']);
        $leaderRole = null;
        if ($memberTeam && $leader) {
            try {
                $leaderRole = $this->userRoleService->getRole($leader, $memberTeam)->value;
            } catch (ModelNotFoundException) {
                $leaderRole = null;
            }
        }

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
        if (! $user->hasRole(Role::Admin) && $team->user_id !== $user->id) {
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
    public function update(ArbeitsgruppeRequest $request, Team $team)
    {
        $user = $request->user();
        $validated = $request->validated();

        // Nicht-Admins dürfen Leiter und Name nicht ändern
        if (! $user->hasRole(Role::Admin)) {
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

        if ($user->hasRole(Role::Admin) && $team->wasChanged('user_id')) {
            $memberTeam = Team::membersTeam();
            $leader = User::find($validated['leader_id']);
            $leaderRole = null;
            if ($memberTeam && $leader) {
                try {
                    $leaderRole = $this->userRoleService->getRole($leader, $memberTeam)->value;
                } catch (ModelNotFoundException) {
                    $leaderRole = null;
                }
            }
            $team->users()->syncWithoutDetaching([
                $validated['leader_id'] => ['role' => $leaderRole],
            ]);
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
        if (! $user->hasRole(Role::Admin) && $team->user_id !== $user->id) {
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
        $adder->add($user, $team, $member->email, Role::Mitwirkender->value);

        return redirect()->route('arbeitsgruppen.edit', $team)
            ->with('status', 'Mitglied hinzugefügt.');
    }
}
