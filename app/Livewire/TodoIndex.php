<?php

namespace App\Livewire;

use App\Enums\TodoStatus;
use App\Models\Activity;
use App\Models\Todo;
use App\Models\UserPoint;
use App\Services\MembersTeamProvider;
use App\Services\TeamPointService;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class TodoIndex extends Component
{

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
        $todo = Todo::findOrFail($todoId);
        $user = Auth::user();

        if ($todo->team_id !== $this->memberTeam->id) {
            $this->dispatch('toast', type: 'error', title: 'Challenge nicht gefunden.');

            return;
        }

        if ($todo->assigned_to || $todo->status !== TodoStatus::Open) {
            $this->dispatch('toast', type: 'error', title: 'Diese Challenge wurde bereits übernommen oder ist nicht mehr verfügbar.');

            return;
        }

        $this->authorize('assign', $todo);

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

        unset($this->todos, $this->assignedTodos, $this->unassignedTodos);
        $this->dispatch('toast', type: 'success', title: 'Challenge wurde erfolgreich übernommen.');
    }

    public function complete(int $todoId): void
    {
        $todo = Todo::where('team_id', $this->memberTeam->id)->find($todoId);
        $user = Auth::user();

        if (! $todo) {
            $this->dispatch('toast', type: 'error', title: 'Challenge nicht gefunden.');

            return;
        }

        if ($todo->assigned_to !== $user->id || $todo->status !== TodoStatus::Assigned) {
            $this->dispatch('toast', type: 'error', title: 'Sie können diese Challenge nicht als erledigt markieren.');

            return;
        }

        $todo->update([
            'status' => TodoStatus::Completed->value,
            'completed_at' => now(),
        ]);

        unset($this->todos, $this->assignedTodos, $this->completedTodos);
        $this->dispatch('toast', type: 'success', title: 'Challenge wurde als erledigt markiert und wartet nun auf Verifizierung.');
    }

    public function verify(int $todoId): void
    {
        $todo = Todo::findOrFail($todoId);
        $user = Auth::user();

        if ($todo->team_id !== $this->memberTeam->id) {
            $this->dispatch('toast', type: 'error', title: 'Challenge nicht gefunden.');

            return;
        }

        if ($todo->status !== TodoStatus::Completed) {
            $this->dispatch('toast', type: 'error', title: 'Diese Challenge kann nicht verifiziert werden.');

            return;
        }

        $this->authorize('verify', Todo::class);

        // Atomare Verifizierung: Status-Wechsel + Punkte-Gutschrift in einer
        // Transaktion. Das bedingte UPDATE (WHERE status = completed) stellt
        // sicher, dass nur genau ein paralleler Aufruf den Übergang vollzieht.
        // updateOrCreate auf todo_id macht die Gutschrift zusätzlich idempotent
        // und harmoniert mit dem Unique-Index auf user_points.todo_id.
        $verified = DB::transaction(function () use ($todo, $user) {
            $updated = Todo::where('id', $todo->id)
                ->where('status', TodoStatus::Completed->value)
                ->update([
                    'status' => TodoStatus::Verified->value,
                    'verified_by' => $user->id,
                    'verified_at' => now(),
                ]);

            if ($updated === 0) {
                return false;
            }

            UserPoint::updateOrCreate(
                ['todo_id' => $todo->id],
                [
                    'user_id' => $todo->assigned_to,
                    'team_id' => $todo->team_id,
                    'points' => $todo->points,
                ],
            );

            Activity::create([
                'user_id' => $todo->assigned_to,
                'subject_type' => Todo::class,
                'subject_id' => $todo->id,
                'action' => 'completed',
            ]);

            return true;
        });

        if (! $verified) {
            $this->dispatch('toast', type: 'error', title: 'Diese Challenge wurde bereits verifiziert.');
            unset($this->todos, $this->completedTodos);

            return;
        }

        $todo->refresh()->load('assignee');
        unset($this->todos, $this->completedTodos, $this->dashboardMetrics, $this->userPoints);
        $this->dispatch('toast', type: 'success', title: "Challenge-Verifizierung erfolgreich! {$todo->points} Baxx wurden {$todo->assignee->name} gutgeschrieben.");
    }

    public function release(int $todoId): void
    {
        $todo = Todo::where('team_id', $this->memberTeam->id)->find($todoId);
        $user = Auth::user();

        if (! $todo) {
            $this->dispatch('toast', type: 'error', title: 'Challenge nicht gefunden.');

            return;
        }

        if ($todo->assigned_to !== $user->id || $todo->status !== TodoStatus::Assigned) {
            $this->dispatch('toast', type: 'error', title: 'Sie können diese Challenge nicht freigeben.');

            return;
        }

        $todo->update([
            'assigned_to' => null,
            'status' => TodoStatus::Open->value,
        ]);

        unset($this->todos, $this->assignedTodos, $this->unassignedTodos);
        $this->dispatch('toast', type: 'success', title: 'Challenge wurde erfolgreich freigegeben.');
    }

    public function deleteTodo(int $todoId): void
    {
        $todo = Todo::findOrFail($todoId);

        if ($todo->team_id !== $this->memberTeam->id) {
            $this->dispatch('toast', type: 'error', title: 'Challenge nicht gefunden.');

            return;
        }

        $this->authorize('delete', $todo);

        if ($todo->status === TodoStatus::Verified) {
            UserPoint::where('todo_id', $todo->id)->delete();
            $todo->delete();
            unset($this->todos, $this->dashboardMetrics, $this->userPoints);
            $this->dispatch('toast', type: 'success', title: 'Challenge wurde erfolgreich gelöscht. Die gutgeschriebenen Baxx wurden abgezogen.');

            return;
        }

        $todo->delete();
        unset($this->todos);
        $this->dispatch('toast', type: 'success', title: 'Challenge wurde erfolgreich gelöscht.');
    }

    public function placeholder()
    {
        return view('components.skeleton-table', ['columns' => 6, 'rows' => 8]);
    }

    public function render()
    {
        return view('livewire.todo-index')
            ->layout('layouts.app', ['title' => 'Challenges & Baxx']);
    }
}
