<?php

namespace App\Livewire;

use App\Actions\Todos\TodoWorkflow;
use App\Enums\TodoStatus;
use App\Livewire\Concerns\InteractsWithTodoWorkflow;
use App\Models\Todo;
use App\Services\MembersTeamProvider;
use App\Services\TeamPointService;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class TodoIndex extends Component
{
    use InteractsWithTodoWorkflow;

    #[Url]
    public string $filter = 'all';

    public function mount()
    {
        if (! in_array($this->filter, ['all', 'assigned', 'open', 'pending'], true)) {
            $this->filter = 'all';
        }

        // Nutzer ohne Verify-Recht dürfen 'pending' nicht sehen, sonst zeigt die UI
        // 'pending' an, während todos() den Filter ignoriert und alle Todos lädt.
        if ($this->filter === 'pending' && ! $this->canVerifyTodos) {
            $this->filter = 'all';
        }
    }

    #[Computed]
    public function memberTeam()
    {
        return app(MembersTeamProvider::class)->getMembersTeamOrAbort();
    }

    #[Computed]
    public function userRole()
    {
        try {
            return app(UserRoleService::class)->getRole(Auth::user(), $this->memberTeam);
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    #[Computed]
    public function canCreateTodos(): bool
    {
        return Auth::user()->can('create', Todo::class);
    }

    #[Computed]
    public function canVerifyTodos(): bool
    {
        return Auth::user()->can('verify', Todo::class);
    }

    #[Computed]
    public function todos()
    {
        $query = Todo::where('team_id', $this->memberTeam->id)
            ->with(['creator', 'assignee', 'verifier', 'category']);

        if ($this->filter === 'pending' && $this->canVerifyTodos) {
            $query->where('status', 'completed');
        }

        return $query->orderBy('status')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    #[Computed]
    public function assignedTodos()
    {
        return $this->todos->where('assigned_to', Auth::id());
    }

    #[Computed]
    public function unassignedTodos()
    {
        return $this->todos->where('status', TodoStatus::Open);
    }

    #[Computed]
    public function completedTodos()
    {
        return $this->todos->filter(fn ($todo) => in_array($todo->status->value, ['completed', 'verified'], true) &&
            $todo->assigned_to !== Auth::id()
        );
    }

    #[Computed]
    public function inProgressTodos()
    {
        return $this->todos->where('status', TodoStatus::Assigned)->where('assigned_to', '!=', Auth::id());
    }

    #[Computed]
    public function dashboardMetrics(): array
    {
        return app(TeamPointService::class)->getDashboardMetrics(Auth::user(), $this->memberTeam);
    }

    #[Computed]
    public function userPoints(): int
    {
        return $this->dashboardMetrics['user_points'];
    }

    public function assign(int $todoId): void
    {
        $todo = $this->runTodoAction(fn () => app(TodoWorkflow::class)->assign(Auth::user(), $todoId));
        unset($this->todos, $this->assignedTodos, $this->unassignedTodos, $this->completedTodos, $this->inProgressTodos, $this->dashboardMetrics, $this->userPoints);

        if ($todo) {
            $this->dispatch('toast', type: 'success', title: 'Challenge wurde erfolgreich übernommen.');
        }
    }

    public function complete(int $todoId): void
    {
        $todo = $this->runTodoAction(fn () => app(TodoWorkflow::class)->complete(Auth::user(), $todoId));
        unset($this->todos, $this->assignedTodos, $this->unassignedTodos, $this->completedTodos, $this->inProgressTodos, $this->dashboardMetrics, $this->userPoints);

        if ($todo) {
            $this->dispatch('toast', type: 'success', title: 'Challenge wurde als erledigt markiert und wartet nun auf Verifizierung.');
        }
    }

    public function verify(int $todoId): void
    {
        $todo = $this->runTodoAction(fn () => app(TodoWorkflow::class)->verify(Auth::user(), $todoId));
        unset($this->todos, $this->assignedTodos, $this->unassignedTodos, $this->completedTodos, $this->inProgressTodos, $this->dashboardMetrics, $this->userPoints);

        if ($todo) {
            $this->dispatch('toast', type: 'success', title: "Challenge-Verifizierung erfolgreich! {$todo->points} Baxx wurden {$todo->assignee->name} gutgeschrieben.");
        }
    }

    public function release(int $todoId): void
    {
        $todo = $this->runTodoAction(fn () => app(TodoWorkflow::class)->release(Auth::user(), $todoId));
        unset($this->todos, $this->assignedTodos, $this->unassignedTodos, $this->completedTodos, $this->inProgressTodos, $this->dashboardMetrics, $this->userPoints);

        if ($todo) {
            $this->dispatch('toast', type: 'success', title: 'Challenge wurde erfolgreich freigegeben.');
        }
    }

    public function deleteTodo(int $todoId): void
    {
        $todo = $this->runTodoAction(fn () => app(TodoWorkflow::class)->delete(Auth::user(), $todoId));
        if (! $todo) {
            return;
        }

        $message = $todo->status === TodoStatus::Verified
            ? 'Challenge wurde erfolgreich gelöscht. Die gutgeschriebenen Baxx wurden abgezogen.'
            : 'Challenge wurde erfolgreich gelöscht.';
        unset($this->todos, $this->assignedTodos, $this->unassignedTodos, $this->completedTodos, $this->inProgressTodos, $this->dashboardMetrics, $this->userPoints);
        $this->dispatch('toast', type: 'success', title: $message);
    }

    public function placeholder()
    {
        return view('components.skeleton-table', ['columns' => 6, 'rows' => 8]);
    }

    public function render()
    {
        return view('livewire.todo-index')
            ->layout('layouts.member', ['title' => 'Challenges & Baxx']);
    }
}
