<?php

namespace App\Livewire\Concerns;

use App\Models\Book;
use App\Support\Romantausch\InputRules;
use App\Support\Romantausch\MutationResult;
use App\Support\Romantausch\WorkflowFailure;
use Closure;
use Livewire\Attributes\Computed;

trait InteractsWithRomantauschForm
{
    #[Computed]
    public function books()
    {
        return Book::whereIn('type', array_column(InputRules::TYPES, 'value'))->orderBy('roman_number')->get();
    }

    #[Computed]
    public function types(): array
    {
        return InputRules::TYPES;
    }

    #[Computed]
    public function seriesOptions(): array
    {
        return collect($this->types)->map(fn ($type) => ['id' => $type->value, 'name' => $type->value])->all();
    }

    #[Computed]
    public function bookOptions(): array
    {
        return $this->books->map(fn ($book) => ['id' => $book->roman_number, 'name' => $book->roman_number.' - '.$book->title])->all();
    }

    #[Computed]
    public function booksBySeries()
    {
        return $this->books->groupBy(fn ($book) => $book->type->value)
            ->map(fn ($books) => $books->pluck('roman_number')->map(fn ($number) => (string) $number)->values());
    }

    private function submitRomantausch(Closure $action, ?string $success = null): void
    {
        $this->resetValidation();
        try {
            /** @var MutationResult $result */
            $result = $action();
        } catch (WorkflowFailure $error) {
            $this->addError($error->field, $error->getMessage());

            return;
        }
        session()->put('romantausch.success', $success ?? 'Stapel-Angebot mit '.$result->details['count'].' Romanen erstellt.');
        if ($result->warning()) {
            session()->flash('warning', $result->warning());
        }
        $this->redirect(route('romantausch.index'));
    }
}
