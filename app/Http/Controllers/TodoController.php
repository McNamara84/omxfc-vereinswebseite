<?php

namespace App\Http\Controllers;

use App\Actions\Todos\TodoWorkflow;
use App\Enums\TodoStatus;
use App\Http\Requests\TodoRequest;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Services\MembersTeamProvider;
use App\Services\TeamPointService;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Auth;

class TodoController extends Controller
{
    public function __construct(
        private TeamPointService $teamPointService,
        private UserRoleService $userRoleService,
        private MembersTeamProvider $membersTeamProvider,
    ) {}

    /**
     * Zeigt die Übersicht der Todos an.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $memberTeam = $this->membersTeamProvider->getMembersTeamOrAbort();

        try {
            $userRole = $this->userRoleService->getRole($user, $memberTeam);
        } catch (ModelNotFoundException) {
            $userRole = null;
        }

        $canCreateTodos = $user->can('create', Todo::class);
        $canVerifyTodos = $user->can('verify', Todo::class);

        // Query-Builder für Todos
        $todosQuery = Todo::where('team_id', $memberTeam->id)
            ->with(['creator', 'assignee', 'verifier']);

        // Filter für "pending" hinzufügen
        $filter = $request->query('filter');
        if ($filter === 'pending' && $canVerifyTodos) {
            $todosQuery->where('status', 'completed');
        }

        // Sortierung anwenden
        $todos = $todosQuery->orderBy('status')
            ->orderBy('created_at', 'desc')
            ->get();

        $assignedTodos = $todos->where('assigned_to', $user->id);
        $unassignedTodos = $todos->where('status', 'open');
        $completedTodos = $todos->filter(fn ($todo) => in_array($todo->status->value, ['completed', 'verified'], true) &&
            $todo->assigned_to !== $user->id
        );

        $dashboardMetrics = $this->teamPointService->getDashboardMetrics($user, $memberTeam);

        return view('todos.index', [
            'todos' => $todos,
            'assignedTodos' => $assignedTodos,
            'unassignedTodos' => $unassignedTodos,
            'completedTodos' => $completedTodos,
            'canCreateTodos' => $canCreateTodos,
            'canVerifyTodos' => $canVerifyTodos,
            'userPoints' => $dashboardMetrics['user_points'],
            'memberTeam' => $memberTeam,
            'userRole' => $userRole,
            'filter' => $filter,
            'activeFilter' => in_array($filter, ['all', 'assigned', 'open', 'pending'], true) ? $filter : 'all',
            'dashboardMetrics' => $dashboardMetrics,
        ]);
    }

    /**
     * Zeigt das Formular zum Erstellen eines neuen Todos.
     */
    #[Authorize('create', Todo::class)]
    public function create()
    {
        $user = Auth::user();
        $memberTeam = $this->membersTeamProvider->getMembersTeamOrAbort();

        $categories = TodoCategory::orderBy('name')->get();

        return view('todos.create', [
            'memberTeam' => $memberTeam,
            'categories' => $categories,
        ]);
    }

    /**
     * Speichert ein neues Todo.
     */
    #[Authorize('create', Todo::class)]
    public function store(TodoRequest $request)
    {
        app(TodoWorkflow::class)->create(Auth::user(), $request->validated());

        return redirect()->route('todos.index')->with('status', 'Challenge wurde erfolgreich erstellt.');
    }

    /**
     * Zeigt die Details eines Todos.
     */
    public function show(Todo $todo)
    {
        $user = Auth::user();
        $memberTeam = $this->membersTeamProvider->getMembersTeamOrAbort();

        if ($todo->team_id !== $memberTeam->id) {
            return redirect()->route('todos.index')
                ->with('error', 'Challenge nicht gefunden.');
        }

        try {
            $userRole = $this->userRoleService->getRole($user, $memberTeam);
        } catch (ModelNotFoundException) {
            $userRole = null;
        }

        $canAssign = ! $todo->assigned_to && $todo->status === TodoStatus::Open && $user->can('assign', $todo);

        $canEdit = $user->can('update', $todo);

        $canComplete = $todo->assigned_to === $user->id && $todo->status === TodoStatus::Assigned;

        $canVerify = $todo->status === TodoStatus::Completed && $user->can('verify', Todo::class);

        $canDelete = $user->can('delete', $todo);

        return view('todos.show', [
            'todo' => $todo,
            'canAssign' => $canAssign,
            'canComplete' => $canComplete,
            'canVerify' => $canVerify,
            'canEdit' => $canEdit,
            'canDelete' => $canDelete,
            'userRole' => $userRole,
        ]);
    }

    /**
     * Formular zum Bearbeiten eines Todos.
     */
    public function edit(Todo $todo)
    {
        $user = Auth::user();
        $memberTeam = $this->membersTeamProvider->getMembersTeamOrAbort();

        if ($todo->team_id !== $memberTeam->id) {
            return redirect()->route('todos.index')
                ->with('error', 'Challenge nicht gefunden.');
        }

        $this->authorize('update', $todo);

        $categories = TodoCategory::orderBy('name')->get();

        return view('todos.edit', [
            'todo' => $todo,
            'categories' => $categories,
        ]);
    }

    /**
     * Aktualisiert ein Todo.
     */
    public function update(TodoRequest $request, Todo $todo)
    {
        app(TodoWorkflow::class)->update(Auth::user(), $todo->id, $request->validated());

        return redirect()->route('todos.show', $todo)->with('status', 'Challenge wurde erfolgreich aktualisiert.');
    }

    /**
     * Übernimmt ein Todo.
     */
    public function assign(Todo $todo)
    {
        app(TodoWorkflow::class)->assign(Auth::user(), $todo->id);

        return redirect()->route('todos.show', $todo)->with('status', 'Challenge wurde erfolgreich übernommen.');
    }

    /**
     * Markiert ein Todo als erledigt.
     */
    public function complete(Todo $todo)
    {
        app(TodoWorkflow::class)->complete(Auth::user(), $todo->id);

        return redirect()->route('todos.show', $todo)->with('status', 'Challenge wurde als erledigt markiert und wartet nun auf Verifizierung.');
    }

    /**
     * Verifiziert ein erledigtes Todo und schreibt Punkte gut.
     */
    #[Authorize('verify', Todo::class)]
    public function verify(Todo $todo)
    {
        app(TodoWorkflow::class)->verify(Auth::user(), $todo->id);

        return redirect()->route('todos.show', $todo)->with('status', 'Challenge wurde verifiziert und die Punkte wurden gutgeschrieben.');
    }

    /**
     * Gibt ein angenommenes Todo wieder frei.
     */
    public function release(Todo $todo)
    {
        app(TodoWorkflow::class)->release(Auth::user(), $todo->id);

        return redirect()->route('todos.index')->with('status', 'Challenge wurde erfolgreich freigegeben und steht nun wieder zur Verfügung.');
    }

    /**
     * Entfernt eine Challenge dauerhaft.
     */
    public function destroy(Todo $todo)
    {
        $deleted = app(TodoWorkflow::class)->delete(Auth::user(), $todo->id);
        $message = $deleted->status === TodoStatus::Verified
            ? 'Challenge wurde erfolgreich gelöscht. Die gutgeschriebenen Baxx wurden abgezogen.'
            : 'Challenge wurde erfolgreich gelöscht.';

        return redirect()->route('todos.index')->with('status', $message);
    }
}
