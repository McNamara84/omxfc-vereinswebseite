<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Models\User;
use App\Services\MembersTeamProvider;
use App\Services\TourAssignmentService;
use App\Services\TourRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TourAdminController extends Controller
{
    public function __construct(
        private readonly MembersTeamProvider $membersTeamProvider,
        private readonly TourRegistry $tourRegistry,
        private readonly TourAssignmentService $tourAssignmentService,
    ) {}

    public function index(Request $request): View
    {
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();
        $suche = trim((string) $request->string('suche'));
        $tourDefinitions = collect($this->tourRegistry->definitions())->values();

        $members = $team->users()
            ->select('users.*')
            ->withPivot('role')
            ->wherePivotNotIn('role', [Role::Anwaerter->value])
            ->when($suche !== '', function ($query) use ($suche) {
                $query->where(function ($memberQuery) use ($suche) {
                    $memberQuery
                        ->where('users.name', 'like', "%{$suche}%")
                        ->orWhere('users.email', 'like', "%{$suche}%");
                });
            })
            ->with([
                'tourAssignments' => fn ($query) => $query
                    ->whereIn('tour_key', $tourDefinitions->pluck('key')->all())
                    ->with('assignedBy')
                    ->orderByDesc('assigned_at'),
            ])
            ->orderBy('users.name')
            ->paginate(12)
            ->through(function (User $member) {
                $member->setAttribute(
                    'members_team_role',
                    $member->pivot?->role ?? $member->mitgliederTeamRole()?->value,
                );

                return $member;
            })
            ->withQueryString();

        return view('admin.touren.index', [
            'filters' => ['suche' => $suche],
            'members' => $members,
            'tourDefinitions' => $tourDefinitions,
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        $tourKeys = collect($this->tourRegistry->definitions())
            ->pluck('key')
            ->all();

        $validated = $request->validate([
            'tour_key' => ['required', 'string', Rule::in($tourKeys)],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $team = $this->membersTeamProvider->getMembersTeamOrAbort();
        $member = $team->users()
            ->select('users.*')
            ->withPivot('role')
            ->where('users.id', $validated['user_id'])
            ->wherePivotNotIn('role', [Role::Anwaerter->value])
            ->firstOrFail();

        /** @var User $member */
        $definition = $this->tourRegistry->definition($validated['tour_key']);

        $this->tourAssignmentService->reassign(
            user: $member,
            tourKey: $definition->key,
            source: TourAssignmentSource::Manual,
            assignedBy: $request->user(),
        );

        return redirect()
            ->route('admin.touren.index', $request->only('suche', 'page'))
            ->with('success', "Tour '{$definition->title}' wurde {$member->name} neu zugewiesen.");
    }
}