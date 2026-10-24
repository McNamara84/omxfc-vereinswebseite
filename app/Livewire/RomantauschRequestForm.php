<?php

namespace App\Livewire;

use App\Actions\Romantausch\RequestWorkflow;
use App\Http\Requests\StoreBookOfferRequest;
use App\Livewire\Concerns\InteractsWithRomantauschForm;
use App\Models\BookRequest;
use App\Support\ConditionOptions;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RomantauschRequestForm extends Component
{
    use InteractsWithRomantauschForm;

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
    public function conditionOptions(): array
    {
        return ConditionOptions::full();
    }

    public function save(): void
    {
        $workflow = app(RequestWorkflow::class);
        $data = $this->only(['series', 'book_number', 'condition']);
        $this->submitRomantausch(
            fn () => $this->isEditing ? $workflow->update(Auth::user(), $this->requestId, $data) : $workflow->create(Auth::user(), $data),
            $this->isEditing ? 'Gesuch aktualisiert.' : 'Gesuch erstellt.',
        );
    }

    public function placeholder()
    {
        return view('components.skeleton-form', ['fields' => 3, 'hasTextarea' => false]);
    }

    public function render()
    {
        return view('livewire.romantausch-request-form')
            ->layout('layouts.member', ['title' => $this->isEditing ? 'Gesuch bearbeiten' : 'Neues Gesuch erstellen']);
    }
}
