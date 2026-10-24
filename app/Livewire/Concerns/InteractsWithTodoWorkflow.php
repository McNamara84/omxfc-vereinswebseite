<?php

namespace App\Livewire\Concerns;

use App\Models\Todo;
use Closure;
use Illuminate\Validation\ValidationException;

trait InteractsWithTodoWorkflow
{
    private function runTodoAction(Closure $action): ?Todo
    {
        try {
            return $action();
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', title: collect($exception->errors())->flatten()->first());

            return null;
        }
    }
}
