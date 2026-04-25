<?php

namespace App\Livewire;

use App\Enums\TodoStatus;
use App\Models\Activity;
use App\Models\Todo;
use App\Models\UserPoint;
use App\Services\MembersTeamProvider;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TodoShow extends Component
{

    #[Locked]
    public int $todoId;

    public bool $confirmingDelete = false;

    public function mount(Todo $todo): void
    {
        $memberTeam = app(MembersTeamProvider::class)->getMembersTeamOrAbort();

        if ($todo->team_id !== $memberTeam->id) {
            $this->redirect(route('todos.index'));

            return;
        }

        $this->todoId = $todo->id;
    }

    #[Computed]
    public function todo(): Todo
    {
        return Todo::with(['creator', 'assignee', 'verifier', 'category'])->findOrFail($this->todoId);
    }

    #[Computed]
    public function canAssign(): bool
    {
        $todo = $this->todo;

        return ! $todo->assigned_to && $todo->status === TodoStatus::Open && Auth::user()->can('assign', $todo);
    }

    #[Computed]
    public function canEdit(): bool
    {
        return Auth::user()->can('update', $this->todo);
    }

    #[Computed]
    public function canComplete(): bool
    {
        return $this->todo->assigned_to === Auth::id() && $this->todo->status === TodoStatus::Assigned;
    }

    #[Computed]
    public function canVerify(): bool
    {
        return $this->todo->status === TodoStatus::Completed && Auth::user()->can('verify', Todo::class);
    }

    #[Computed]
    public function canDelete(): bool
    {
        return Auth::user()->can('delete', $this->todo);
    }

    #[Computed]
    public function canRelease(): bool
    {
        return $this->todo->assigned_to === Auth::id() && $this->todo->status === TodoStatus::Assigned;
    }

    public function assign(): void
    {
        $todo = $this->todo;
        $this->authorize('assign', $todo);

        if ($todo->assigned_to || $todo->status !== TodoStatus::Open) {
            $this->dispatch('toast', type: 'error', title: 'Diese Challenge wurde bereits übernommen oder ist nicht mehr verfügbar.');

            return;
        }

        $todo->update([
            'assigned_to' => Auth::id(),
            'status' => 'assigned',
        ]);

        Activity::create([
            'user_id' => Auth::id(),
            'subject_type' => Todo::class,
            'subject_id' => $todo->id,
            'action' => 'accepted',
        ]);

        unset($this->todo, $this->canAssign, $this->canRelease, $this->canComplete);
        $this->dispatch('toast', type: 'success', title: 'Challenge wurde erfolgreich übernommen.');
    }

    public function complete(): void
    {
        $todo = $this->todo;

        if ($todo->assigned_to !== Auth::id() || $todo->status !== TodoStatus::Assigned) {
            $this->dispatch('toast', type: 'error', title: 'Sie können diese Challenge nicht als erledigt markieren.');

            return;
        }

        $todo->update([
            'status' => TodoStatus::Completed->value,
            'completed_at' => now(),
        ]);

        unset($this->todo, $this->canComplete, $this->canVerify);
        $this->dispatch('toast', type: 'success', title: 'Challenge wurde als erledigt markiert und wartet nun auf Verifizierung.');
    }

    public function verify(): void
    {
        $todo = $this->todo;
        $this->authorize('verify', Todo::class);

        if ($todo->status !== TodoStatus::Completed) {
            $this->dispatch('toast', type: 'error', title: 'Diese Challenge kann nicht verifiziert werden.');

            return;
        }

        UserPoint::create([
            'user_id' => $todo->assigned_to,
            'team_id' => $todo->team_id,
            'todo_id' => $todo->id,
            'points' => $todo->points,
        ]);

        $todo->update([
            'status' => TodoStatus::Verified->value,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        Activity::create([
            'user_id' => $todo->assigned_to,
            'subject_type' => Todo::class,
            'subject_id' => $todo->id,
            'action' => 'completed',
        ]);

        unset($this->todo, $this->canVerify);
        $this->dispatch('toast', type: 'success', title: "Challenge wurde verifiziert und {$todo->points} Baxx wurden gutgeschrieben.");
    }

    public function release(): void
    {
        $todo = $this->todo;

        if ($todo->assigned_to !== Auth::id() || $todo->status !== TodoStatus::Assigned) {
            $this->dispatch('toast', type: 'error', title: 'Sie können diese Challenge nicht freigeben.');

            return;
        }

        $todo->update([
            'assigned_to' => null,
            'status' => TodoStatus::Open->value,
        ]);

        unset($this->todo, $this->canRelease, $this->canAssign);
        $this->dispatch('toast', type: 'success', title: 'Challenge wurde erfolgreich freigegeben.');
    }

    public function deleteTodo(): void
    {
        $todo = $this->todo;
        $this->authorize('delete', $todo);

        $wasVerified = $todo->status === TodoStatus::Verified;
        if ($wasVerified) {
            $todo->userPoint()->delete();
        }

        $todo->delete();

        $message = $wasVerified
            ? 'Challenge wurde erfolgreich gelöscht. Die gutgeschriebenen Baxx wurden abgezogen.'
            : 'Challenge wurde erfolgreich gelöscht.';

        session()->flash('toast', ['type' => 'success', 'title' => $message]);
        $this->redirect(route('todos.index'), navigate: true);
    }

    public function placeholder()
    {
        return view('components.skeleton-detail', ['sections' => 3]);
    }

    public function render()
    {
        return view('livewire.todo-show')
            ->layout('layouts.app', ['title' => $this->todo->title]);
    }
}
