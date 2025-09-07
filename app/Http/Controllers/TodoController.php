<?php

namespace App\Http\Controllers;

use App\Enums\TodoStatus;
use App\Models\Activity;
use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\UserPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TodoController extends Controller
{
    /**
     * Zeigt die Übersicht der Todos an.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $memberTeam = Team::where('name', 'Mitglieder')->first();

        if (! $memberTeam) {
            return view('todos.index', [
                'todos' => collect([]),
                'canCreateTodos' => false,
                'userPoints' => 0,
                'memberTeam' => null,
            ]);
        }

        $membership = $memberTeam->users()->where('user_id', $user->id)->first();
        $userRole = $membership ? $membership->membership->role : null;

        $canCreateTodos = in_array($userRole, ['Kassenwart', 'Vorstand', 'Admin']);
        $canVerifyTodos = in_array($userRole, ['Kassenwart', 'Vorstand', 'Admin']);

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
        $completedTodos = $todos->whereIn('status', ['completed', 'verified'])
            ->where('assigned_to', '!=', $user->id);

        $userPoints = UserPoint::where('user_id', $user->id)
            ->where('team_id', $memberTeam->id)
            ->sum('points');

        return view('todos.index', [
            'todos' => $todos,
            'assignedTodos' => $assignedTodos,
            'unassignedTodos' => $unassignedTodos,
            'completedTodos' => $completedTodos,
            'canCreateTodos' => $canCreateTodos,
            'canVerifyTodos' => $canVerifyTodos,
            'userPoints' => $userPoints,
            'memberTeam' => $memberTeam,
            'userRole' => $userRole,
            'filter' => $filter,
        ]);
    }

    /**
     * Zeigt das Formular zum Erstellen eines neuen Todos.
     */
    public function create()
    {
        $user = Auth::user();
        $memberTeam = Team::where('name', 'Mitglieder')->first();

        if (! $memberTeam) {
            return redirect()->route('todos.index')
                ->with('error', 'Team "Mitglieder" nicht gefunden.');
        }

        $membership = $memberTeam->users()->where('user_id', $user->id)->first();
        $userRole = $membership ? $membership->membership->role : null;

        if (! in_array($userRole, ['Kassenwart', 'Vorstand', 'Admin'])) {
            return redirect()->route('todos.index')
                ->with('error', 'Du hast keine Berechtigung, Challenges zu erstellen.');
        }

        $categories = TodoCategory::orderBy('name')->get();

        return view('todos.create', [
            'memberTeam' => $memberTeam,
            'categories' => $categories,
        ]);
    }

    /**
     * Speichert ein neues Todo.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $memberTeam = Team::where('name', 'Mitglieder')->first();

        if (! $memberTeam) {
            return redirect()->route('todos.index')
                ->with('error', 'Team "Mitglieder" nicht gefunden.');
        }

        $membership = $memberTeam->users()->where('user_id', $user->id)->first();
        $userRole = $membership ? $membership->membership->role : null;

        if (! in_array($userRole, ['Kassenwart', 'Vorstand', 'Admin'])) {
            return redirect()->route('todos.index')
                ->with('error', 'Du hast keine Berechtigung, Challenges zu erstellen.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'points' => 'required|integer|min:1|max:1000',
            'category_id' => 'required|exists:todo_categories,id',
        ]);

        Todo::create([
            'team_id' => $memberTeam->id,
            'created_by' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'points' => $request->points,
            'category_id' => $request->category_id,
            'status' => 'open',
        ]);

        return redirect()->route('todos.index')
            ->with('status', 'Challenge wurde erfolgreich erstellt.');
    }

    /**
     * Zeigt die Details eines Todos.
     */
    public function show(Todo $todo)
    {
        $user = Auth::user();
        $memberTeam = Team::where('name', 'Mitglieder')->first();

        if (! $memberTeam || $todo->team_id !== $memberTeam->id) {
            return redirect()->route('todos.index')
                ->with('error', 'Challenge nicht gefunden.');
        }

        $membership = $memberTeam->users()->where('user_id', $user->id)->first();
        $userRole = $membership ? $membership->membership->role : null;

        $canAssign = ! $todo->assigned_to && $todo->status === TodoStatus::Open && in_array($userRole, ['Mitglied', 'Ehrenmitglied', 'Kassenwart', 'Vorstand', 'Admin']);

        $canEdit = $todo->created_by === $user->id || in_array($userRole, ['Kassenwart', 'Vorstand', 'Admin']);

        $canComplete = $todo->assigned_to === $user->id && $todo->status === TodoStatus::Assigned;

        $canVerify = $todo->status === TodoStatus::Completed &&
            in_array($userRole, ['Kassenwart', 'Vorstand', 'Admin']);

        return view('todos.show', [
            'todo' => $todo,
            'canAssign' => $canAssign,
            'canComplete' => $canComplete,
            'canVerify' => $canVerify,
            'canEdit' => $canEdit,
            'userRole' => $userRole,
        ]);
    }

    /**
     * Formular zum Bearbeiten eines Todos.
     */
    public function edit(Todo $todo)
    {
        $user = Auth::user();
        $memberTeam = Team::where('name', 'Mitglieder')->first();

        if (! $memberTeam || $todo->team_id !== $memberTeam->id) {
            return redirect()->route('todos.index')
                ->with('error', 'Challenge nicht gefunden.');
        }

        $membership = $memberTeam->users()->where('user_id', $user->id)->first();
        $userRole = $membership ? $membership->membership->role : null;

        if ($todo->created_by !== $user->id && ! in_array($userRole, ['Kassenwart', 'Vorstand', 'Admin'])) {
            abort(403);
        }

        $categories = TodoCategory::orderBy('name')->get();

        return view('todos.edit', [
            'todo' => $todo,
            'categories' => $categories,
        ]);
    }

    /**
     * Aktualisiert ein Todo.
     */
    public function update(Request $request, Todo $todo)
    {
        $user = Auth::user();
        $memberTeam = Team::where('name', 'Mitglieder')->first();

        if (! $memberTeam || $todo->team_id !== $memberTeam->id) {
            return redirect()->route('todos.index')
                ->with('error', 'Challenge nicht gefunden.');
        }

        $membership = $memberTeam->users()->where('user_id', $user->id)->first();
        $userRole = $membership ? $membership->membership->role : null;

        if ($todo->created_by !== $user->id && ! in_array($userRole, ['Kassenwart', 'Vorstand', 'Admin'])) {
            abort(403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'points' => 'required|integer|min:1|max:1000',
            'category_id' => 'required|exists:todo_categories,id',
        ]);

        $todo->update([
            'title' => $request->title,
            'description' => $request->description,
            'points' => $request->points,
            'category_id' => $request->category_id,
        ]);

        return redirect()->route('todos.show', $todo)
            ->with('status', 'Challenge wurde erfolgreich aktualisiert.');
    }

    /**
     * Übernimmt ein Todo.
     */
    public function assign(Todo $todo)
    {
        $user = Auth::user();
        $memberTeam = Team::where('name', 'Mitglieder')->first();

        if (! $memberTeam || $todo->team_id !== $memberTeam->id) {
            return redirect()->route('todos.index')
                ->with('error', 'Challenge nicht gefunden.');
        }

        if ($todo->assigned_to || $todo->status !== TodoStatus::Open) {
            return redirect()->route('todos.show', $todo)
                ->with('error', 'Diese Challenge wurde bereits übernommen oder ist nicht mehr verfügbar.');
        }

        $membership = $memberTeam->users()->where('user_id', $user->id)->first();
        $userRole = $membership ? $membership->membership->role : null;

        if (! in_array($userRole, ['Mitglied', 'Ehrenmitglied', 'Kassenwart', 'Vorstand', 'Admin'])) {
            return redirect()->route('todos.show', $todo)
                ->with('error', 'Sie haben keine Berechtigung, diese Challenge zu übernehmen.');
        }

        $todo->update([
            'assigned_to' => $user->id,
            'status' => 'assigned',
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => Todo::class,
            'subject_id' => $todo->id,
            'action' => 'accepted',
        ]);

        return redirect()->route('todos.show', $todo)
            ->with('status', 'Challenge wurde erfolgreich übernommen.');
    }

    /**
     * Markiert ein Todo als erledigt.
     */
    public function complete(Todo $todo)
    {
        $user = Auth::user();

        if ($todo->assigned_to !== $user->id || $todo->status !== TodoStatus::Assigned) {
            return redirect()->route('todos.show', $todo)
                ->with('error', 'Sie können diese Challenge nicht als erledigt markieren.');
        }

        $todo->update([
            'status' => TodoStatus::Completed->value,
            'completed_at' => now(),
        ]);

        return redirect()->route('todos.show', $todo)
            ->with('status', 'Challenge wurde als erledigt markiert und wartet nun auf Verifizierung.');
    }

    /**
     * Verifiziert ein erledigtes Todo und schreibt Punkte gut.
     */
    public function verify(Todo $todo)
    {
        $user = Auth::user();
        $memberTeam = Team::where('name', 'Mitglieder')->first();

        if (! $memberTeam || $todo->team_id !== $memberTeam->id) {
            return redirect()->route('todos.index')
                ->with('error', 'Challenge nicht gefunden.');
        }

        if ($todo->status !== TodoStatus::Completed) {
            return redirect()->route('todos.show', $todo)
                ->with('error', 'Diese Challenge kann nicht verifiziert werden.');
        }

        $membership = $memberTeam->users()->where('user_id', $user->id)->first();
        $userRole = $membership ? $membership->membership->role : null;

        if (! in_array($userRole, ['Kassenwart', 'Vorstand', 'Admin'])) {
            abort(403);
        }

        // Punkte gutschreiben
        UserPoint::create([
            'user_id' => $todo->assigned_to,
            'team_id' => $todo->team_id,
            'todo_id' => $todo->id,
            'points' => $todo->points,
        ]);

        $todo->update([
            'status' => TodoStatus::Verified->value,
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        Activity::create([
            'user_id' => $todo->assigned_to,
            'subject_type' => Todo::class,
            'subject_id' => $todo->id,
            'action' => 'completed',
        ]);

        return redirect()->route('todos.show', $todo)
            ->with('status', 'Challenge wurde verifiziert und die Punkte wurden gutgeschrieben.');
    }

    /**
     * Gibt ein angenommenes Todo wieder frei.
     */
    public function release(Todo $todo)
    {
        $user = Auth::user();

        if ($todo->assigned_to !== $user->id || $todo->status !== TodoStatus::Assigned) {
            return redirect()->route('todos.show', $todo)
                ->with('error', 'Du kannst diese Challenge nicht freigeben, da sie Ihnen nicht zugewiesen ist oder nicht im Bearbeitungsstatus ist.');
        }

        $todo->update([
            'assigned_to' => null,
            'status' => 'open',
        ]);

        return redirect()->route('todos.index')
            ->with('status', 'Challenge wurde erfolgreich freigegeben und steht nun wieder zur Verfügung.');
    }
}
