<?php

namespace App\Livewire;

use App\Actions\Todos\TodoWorkflow;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Services\MembersTeamProvider;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TodoForm extends Component
{
    #[Locked]
    public ?int $todoId = null;

    public string $title = '';

    public ?string $description = null;

    public int $points = 1;

    public ?int $category_id = null;

    public function mount(?Todo $todo = null): void
    {
        if ($todo?->exists) {
            $memberTeam = app(MembersTeamProvider::class)->getMembersTeamOrAbort();
            if ($todo->team_id !== $memberTeam->id) {
                $this->redirect(route('todos.index'));

                return;
            }

            $this->authorize('update', $todo);

            $this->todoId = $todo->id;
            $this->title = $todo->title;
            $this->description = $todo->description;
            $this->points = $todo->points;
            $this->category_id = $todo->category_id;
        } else {
            $this->authorize('create', Todo::class);
        }
    }

    public function save(): void
    {
        $validated = $this->validate(TodoWorkflow::rules());
        $workflow = app(TodoWorkflow::class);
        if ($this->todoId) {
            $todo = $workflow->update(Auth::user(), $this->todoId, $validated);
            session()->flash('toast', ['type' => 'success', 'title' => 'Challenge wurde erfolgreich aktualisiert.']);
            $this->redirect(route('todos.show', $todo), navigate: true);
        } else {
            $workflow->create(Auth::user(), $validated);
            session()->flash('toast', ['type' => 'success', 'title' => 'Challenge wurde erfolgreich erstellt.']);
            $this->redirect(route('todos.index'), navigate: true);
        }
    }

    public function render()
    {
        $categories = TodoCategory::orderBy('name')->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->toArray();

        $title = $this->todoId ? 'Challenge bearbeiten' : 'Neue Challenge erstellen';
        $backRoute = $this->todoId ? route('todos.show', $this->todoId) : route('todos.index');

        return view('livewire.todo-form', [
            'categories' => $categories,
            'formTitle' => $title,
            'backRoute' => $backRoute,
        ])->layout('layouts.member', ['title' => $title]);
    }

    public function placeholder()
    {
        return view('components.skeleton-form', ['fields' => 5]);
    }
}
