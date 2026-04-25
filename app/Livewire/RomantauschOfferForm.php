<?php

namespace App\Livewire;

use App\Http\Requests\StoreBookOfferRequest;
use App\Models\Activity;
use App\Models\BaxxEarningRule;
use App\Models\Book;
use App\Models\BookOffer;
use App\Services\Romantausch\BookPhotoService;
use App\Services\Romantausch\SwapMatchingService;
use App\Support\ConditionOptions;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class RomantauschOfferForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $offerId = null;

    public string $series = '';

    public ?int $book_number = null;

    public string $condition = 'Z0';

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
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
        $photoService = app(BookPhotoService::class);
        $matchingService = app(SwapMatchingService::class);

        $allowedTypes = array_map(fn ($type) => $type->value, StoreBookOfferRequest::ALLOWED_TYPES);
        $maxFileSize = BookPhotoService::MAX_FILE_SIZE_KB;
        $allowedExtensions = implode(',', BookPhotoService::ALLOWED_EXTENSIONS);

        $rules = [
            'series' => ['required', 'in:'.implode(',', $allowedTypes)],
            'book_number' => 'required|integer|min:1',
            'condition' => 'required|string',
            // Neue Uploads serverseitig auf maxNewPhotos begrenzen, damit Gesamtzahl
            // (bereits behaltene + neue) nie BookPhotoService::MAX_PHOTOS überschreitet.
            'photos' => 'array|max:'.$this->maxNewPhotos,
            'photos.*' => "nullable|file|mimes:{$allowedExtensions}|max:{$maxFileSize}",
        ];

        $this->validate($rules);

        $book = Book::where('roman_number', $this->book_number)
            ->where('type', $this->series)
            ->first();

        if (! $book) {
            $this->addError('book_number', 'Ausgewählter Roman nicht gefunden.');

            return;
        }

        if ($this->isEditing) {
            $offer = BookOffer::findOrFail($this->offerId);
            $this->authorize('update', $offer);

            // Handle photo updates
            $photosToKeep = collect($offer->photos ?? [])
                ->reject(fn ($path) => in_array($path, $this->remove_photos))
                ->values()
                ->toArray();

            $photosToDelete = collect($this->remove_photos)
                ->filter(fn ($path) => in_array($path, $offer->photos ?? []))
                ->values()
                ->toArray();

            $newPhotoPaths = [];
            if (! empty($this->photos)) {
                try {
                    $newPhotoPaths = $photoService->uploadPhotos(
                        array_filter($this->photos, fn ($p) => $p instanceof \Illuminate\Http\UploadedFile),
                    );
                } catch (\RuntimeException $e) {
                    $this->addError('photos', 'Foto-Upload fehlgeschlagen. Bitte versuche es erneut.');

                    return;
                }
            }

            $offer->update([
                'series' => $this->series,
                'book_number' => $this->book_number,
                'book_title' => $book->title,
                'condition' => $this->condition,
                'photos' => array_merge($photosToKeep, $newPhotoPaths),
            ]);

            $photoService->deletePhotos($photosToDelete);

            $offer->refresh();
            $matchingService->matchSwap($offer, 'offer');
        } else {
            $photoPaths = [];
            if (! empty($this->photos)) {
                try {
                    $photoPaths = $photoService->uploadPhotos(
                        array_filter($this->photos, fn ($p) => $p instanceof \Illuminate\Http\UploadedFile),
                    );
                } catch (\RuntimeException $e) {
                    $this->addError('photos', 'Foto-Upload fehlgeschlagen. Bitte versuche es erneut.');

                    return;
                }
            }

            $offer = BookOffer::create([
                'user_id' => Auth::id(),
                'series' => $this->series,
                'book_number' => $this->book_number,
                'book_title' => $book->title,
                'condition' => $this->condition,
                'photos' => $photoPaths,
            ]);

            $this->awardPointsIfMilestone();
            $matchingService->matchSwap($offer, 'offer');

            Activity::create([
                'user_id' => Auth::id(),
                'subject_type' => BookOffer::class,
                'subject_id' => $offer->id,
            ]);
        }

        session()->flash('success', $this->isEditing ? 'Angebot aktualisiert.' : 'Angebot erstellt.');
        $this->redirect(route('romantausch.index'));
    }

    private function awardPointsIfMilestone(): void
    {
        $offerCount = BookOffer::where('user_id', Auth::id())->count();
        if ($offerCount % 10 === 0) {
            $points = BaxxEarningRule::getPointsFor('romantausch_offer');
            if ($points > 0) {
                Auth::user()->incrementTeamPoints($points);
            }
        }
    }

    public function placeholder()
    {
        return view('components.skeleton-form', ['fields' => 4]);
    }

    public function render()
    {
        return view('livewire.romantausch-offer-form')
            ->layout('layouts.app', ['title' => $this->isEditing ? 'Angebot bearbeiten' : 'Neues Angebot erstellen']);
    }
}
