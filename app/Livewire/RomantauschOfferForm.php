<?php

namespace App\Livewire;

use App\Actions\Romantausch\OfferWorkflow;
use App\Http\Requests\StoreBookOfferRequest;
use App\Livewire\Concerns\InteractsWithRomantauschForm;
use App\Models\BookOffer;
use App\Services\Romantausch\BookPhotoService;
use App\Support\ConditionOptions;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class RomantauschOfferForm extends Component
{
    use InteractsWithRomantauschForm;
    use WithFileUploads;

    #[Locked]
    public ?int $offerId = null;

    public string $series = '';

    public ?int $book_number = null;

    public string $condition = 'Z0';

    /** @var array<TemporaryUploadedFile> */
    public $photos = [];

    /** @var array<string> */
    public array $remove_photos = [];

    public function mount(?BookOffer $offer = null): void
    {
        if ($offer && $offer->exists) {
            $this->authorize('update', $offer);

            if ($offer->completed || $offer->swap) {
                session()->flash('error', 'Angebote in laufenden oder abgeschlossenen Tauschaktionen können nicht bearbeitet werden.');
                $this->redirect(route('romantausch.index'));

                return;
            }

            $this->offerId = $offer->id;
            $this->series = $offer->series;
            $this->book_number = $offer->book_number;
            $this->condition = $offer->condition;
        } else {
            $types = StoreBookOfferRequest::ALLOWED_TYPES;
            $this->series = $types[0]->value ?? '';
        }
    }

    #[Computed]
    public function isEditing(): bool
    {
        return $this->offerId !== null;
    }

    #[Computed]
    public function existingOffer(): ?BookOffer
    {
        return $this->offerId ? BookOffer::find($this->offerId) : null;
    }

    #[Computed]
    public function conditionOptions(): array
    {
        return ConditionOptions::full();
    }

    #[Computed]
    public function existingPhotos(): array
    {
        return $this->existingOffer?->photos ?? [];
    }

    #[Computed]
    public function keptPhotosCount(): int
    {
        return collect($this->existingPhotos)
            ->reject(fn ($path) => in_array($path, $this->remove_photos))
            ->count();
    }

    #[Computed]
    public function maxNewPhotos(): int
    {
        return max(0, BookPhotoService::MAX_PHOTOS - $this->keptPhotosCount);
    }

    public function save(): void
    {
        $workflow = app(OfferWorkflow::class);
        $data = $this->only(['series', 'book_number', 'condition', 'photos', 'remove_photos']);
        $this->submitRomantausch(
            fn () => $this->isEditing ? $workflow->update(Auth::user(), $this->offerId, $data) : $workflow->create(Auth::user(), $data),
            $this->isEditing ? 'Angebot aktualisiert.' : 'Angebot erstellt.',
        );
    }

    public function placeholder()
    {
        return view('components.skeleton-form', ['fields' => 4]);
    }

    public function render()
    {
        return view('livewire.romantausch-offer-form')
            ->layout('layouts.member', ['title' => $this->isEditing ? 'Angebot bearbeiten' : 'Neues Angebot erstellen']);
    }
}
