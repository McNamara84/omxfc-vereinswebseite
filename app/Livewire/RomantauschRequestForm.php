<?php

namespace App\Livewire;

use App\Http\Requests\StoreBookOfferRequest;
use App\Models\Activity;
use App\Models\Book;
use App\Models\BookRequest;
use App\Services\Romantausch\SwapMatchingService;
use App\Support\ConditionOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RomantauschRequestForm extends Component
{
    #[Locked]
    public ?int $requestId = null;

    public string $series = '';

    public ?int $book_number = null;

    public string $condition = 'Z0';

    public function mount(?BookRequest $bookRequest = null): void
    {
        if ($bookRequest && $bookRequest->exists) {
            $this->authorize('update', $bookRequest);

            if ($bookRequest->completed || $bookRequest->swap) {
                session()->flash('error', 'Gesuche in laufenden oder abgeschlossenen Tauschaktionen können nicht bearbeitet werden.');
                $this->redirect(route('romantausch.index'));

                return;
            }

            $this->requestId = $bookRequest->id;
            $this->series = $bookRequest->series;
            $this->book_number = $bookRequest->book_number;
            $this->condition = $bookRequest->condition;
        } else {
            $types = StoreBookOfferRequest::ALLOWED_TYPES;
            $this->series = $types[0]->value ?? '';
        }
    }

    #[Computed]
    public function isEditing(): bool
    {
        return $this->requestId !== null;
    }

    #[Computed]
    public function books()
    {
        $typeValues = array_map(fn ($type) => $type->value, StoreBookOfferRequest::ALLOWED_TYPES);

        return Book::whereIn('type', $typeValues)->orderBy('roman_number')->get();
    }

    #[Computed]
    public function types(): array
    {
        return StoreBookOfferRequest::ALLOWED_TYPES;
    }

    #[Computed]
    public function conditionOptions(): array
    {
        return ConditionOptions::full();
    }

    #[Computed]
    public function seriesOptions(): array
    {
        return collect($this->types)->map(fn ($t) => ['id' => $t->value, 'name' => $t->value])->toArray();
    }

    #[Computed]
    public function bookOptions(): array
    {
        return $this->books->map(fn ($b) => ['id' => $b->roman_number, 'name' => $b->roman_number.' - '.$b->title])->toArray();
    }

    #[Computed]
    public function booksBySeries()
    {
        return $this->books->groupBy(fn ($b) => $b->type->value)
            ->map(fn ($group) => $group->pluck('roman_number')->map(fn ($n) => (string) $n)->values());
    }

    public function save(): void
    {
        $allowedTypes = array_map(fn ($type) => $type->value, StoreBookOfferRequest::ALLOWED_TYPES);

        $this->validate([
            'series' => ['required', Rule::in($allowedTypes)],
            'book_number' => 'required|integer|min:1',
            'condition' => 'required|string',
        ], [
            'series.required' => 'Bitte wähle eine Serie aus.',
            'book_number.required' => 'Bitte wähle einen Roman aus.',
            'condition.required' => 'Bitte gib den gewünschten Zustand an.',
        ]);

        $book = Book::where('roman_number', $this->book_number)
            ->where('type', $this->series)
            ->first();

        if (! $book) {
            $this->addError('book_number', 'Ausgewählter Roman nicht gefunden.');

            return;
        }

        $matchingService = app(SwapMatchingService::class);

        if ($this->isEditing) {
            $bookRequest = BookRequest::findOrFail($this->requestId);
            $this->authorize('update', $bookRequest);

            $bookRequest->update([
                'series' => $this->series,
                'book_number' => $this->book_number,
                'book_title' => $book->title,
                'condition' => $this->condition,
            ]);

            $matchingService->matchSwap($bookRequest, 'request');
        } else {
            $bookRequest = BookRequest::create([
                'user_id' => Auth::id(),
                'series' => $this->series,
                'book_number' => $this->book_number,
                'book_title' => $book->title,
                'condition' => $this->condition,
            ]);

            Activity::create([
                'user_id' => Auth::id(),
                'subject_type' => BookRequest::class,
                'subject_id' => $bookRequest->id,
            ]);

            $matchingService->matchSwap($bookRequest, 'request');
        }

        session()->flash('success', $this->isEditing ? 'Gesuch aktualisiert.' : 'Gesuch erstellt.');
        $this->redirect(route('romantausch.index'));
    }

    public function placeholder()
    {
        return view('components.skeleton-form', ['fields' => 3, 'hasTextarea' => false]);
    }

    public function render()
    {
        return view('livewire.romantausch-request-form')
            ->layout('layouts.app', ['title' => $this->isEditing ? 'Gesuch bearbeiten' : 'Neues Gesuch erstellen']);
    }
}
