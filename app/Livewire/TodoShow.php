<?php

namespace App\Livewire;

use App\Actions\Todos\TodoWorkflow;
use App\Enums\TodoStatus;
use App\Livewire\Concerns\InteractsWithTodoWorkflow;
use App\Models\Todo;
use App\Services\MembersTeamProvider;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TodoShow extends Component
{
    use InteractsWithTodoWorkflow;

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
        $todo = $this->runTodoAction(fn () => app(TodoWorkflow::class)->assign(Auth::user(), $this->todoId));
        unset($this->todo, $this->canAssign, $this->canRelease, $this->canComplete, $this->canVerify);

        if ($todo) {
            $this->dispatch('toast', type: 'success', title: 'Challenge wurde erfolgreich übernommen.');
        }
    }

    public function complete(): void
    {
        $todo = $this->runTodoAction(fn () => app(TodoWorkflow::class)->complete(Auth::user(), $this->todoId));
        unset($this->todo, $this->canAssign, $this->canRelease, $this->canComplete, $this->canVerify);

        if ($todo) {
            $this->dispatch('toast', type: 'success', title: 'Challenge wurde als erledigt markiert und wartet nun auf Verifizierung.');
        }
    }

    public function verify(): void
    {
        $todo = $this->runTodoAction(fn () => app(TodoWorkflow::class)->verify(Auth::user(), $this->todoId));
        unset($this->todo, $this->canAssign, $this->canRelease, $this->canComplete, $this->canVerify);

        if ($todo) {
            $this->dispatch('toast', type: 'success', title: "Challenge wurde verifiziert und {$todo->points} Baxx wurden gutgeschrieben.");
        }
    }

    public function release(): void
    {
        $todo = $this->runTodoAction(fn () => app(TodoWorkflow::class)->release(Auth::user(), $this->todoId));
        unset($this->todo, $this->canAssign, $this->canRelease, $this->canComplete, $this->canVerify);

        if ($todo) {
            $this->dispatch('toast', type: 'success', title: 'Challenge wurde erfolgreich freigegeben.');
        }
    }

    public function deleteTodo(): void
    {
        $todo = $this->runTodoAction(fn () => app(TodoWorkflow::class)->delete(Auth::user(), $this->todoId));
        if (! $todo) {
            return;
        }

        $message = $todo->status === TodoStatus::Verified
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
            ->layout('layouts.member', ['title' => $this->todo->title]);
    }
}
