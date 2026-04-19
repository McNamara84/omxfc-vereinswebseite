<?php

namespace App\Livewire;

use App\Http\Requests\StoreBookOfferRequest;
use App\Models\Book;
use App\Models\BookOffer;
use App\Services\Romantausch\BookPhotoService;
use App\Services\Romantausch\BundleService;
use App\Support\ConditionOptions;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class RomantauschBundleForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?string $bundleId = null;

    public string $series = '';

    public string $book_numbers = '';

    public string $condition = 'Z0';

    public ?string $condition_max = null;

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public $photos = [];

    /** @var array<string> */
    public array $remove_photos = [];

    public function mount(?string $bundleId = null): void
    {
        if ($bundleId) {
            $offers = BookOffer::where('bundle_id', $bundleId)
                ->where('user_id', Auth::id())
                ->orderBy('book_number')
                ->get();

            if ($offers->isEmpty()) {
                abort(404);
            }

            $this->authorize('update', $offers->first());

            $bundleService = app(BundleService::class);
            if ($bundleService->bundleHasActiveSwaps($bundleId, Auth::id())) {
                session()->flash('error', 'Stapel mit laufenden Tauschaktionen können nicht bearbeitet werden.');
                $this->redirect(route('romantausch.index'));

                return;
            }

            $this->bundleId = $bundleId;
            $this->series = $offers->first()->series;
            $this->condition = $offers->first()->condition;
            $this->condition_max = $offers->first()->condition_max;
            $this->book_numbers = $bundleService->formatBookNumbersRange($offers);
        } else {
            $types = StoreBookOfferRequest::ALLOWED_TYPES;
            $this->series = $types[0]->value ?? '';
        }
    }

    #[Computed]
    public function isEditing(): bool
    {
        return $this->bundleId !== null;
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
        return ConditionOptions::standard();
    }

    #[Computed]
    public function conditionMaxOptions(): array
    {
        return ConditionOptions::full();
    }

    #[Computed]
    public function seriesOptions(): array
    {
        return collect($this->types)->map(fn ($t) => ['id' => $t->value, 'name' => $t->value])->toArray();
    }

    #[Computed]
    public function existingPhotos(): array
    {
        if (! $this->bundleId) {
            return [];
        }

        $firstOffer = BookOffer::where('bundle_id', $this->bundleId)
            ->where('user_id', Auth::id())
            ->first();

        return $firstOffer?->photos ?? [];
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
        $bundleService = app(BundleService::class);
        $photoService = app(BookPhotoService::class);

        $allowedTypes = array_map(fn ($type) => $type->value, StoreBookOfferRequest::ALLOWED_TYPES);
        $maxFileSize = BookPhotoService::MAX_FILE_SIZE_KB;
        $allowedExtensions = implode(',', BookPhotoService::ALLOWED_EXTENSIONS);

        $this->validate([
            'series' => ['required', 'in:'.implode(',', $allowedTypes)],
            'book_numbers' => 'required|string',
            'condition' => 'required|string',
            'condition_max' => 'nullable|string',
            'photos' => 'array|max:'.BookPhotoService::MAX_PHOTOS,
            'photos.*' => "nullable|file|mimes:{$allowedExtensions}|max:{$maxFileSize}",
        ]);

        $bookNumbers = $bundleService->parseBookNumbers($this->book_numbers);

        if (empty($bookNumbers)) {
            $this->addError('book_numbers', 'Keine gültigen Roman-Nummern gefunden. Bitte gib Nummern im Format "1-50, 52, 55" ein.');

            return;
        }

        if (count($bookNumbers) < BundleService::MIN_BUNDLE_SIZE) {
            $this->addError('book_numbers', 'Ein Stapel-Angebot muss mindestens '.BundleService::MIN_BUNDLE_SIZE.' Romane enthalten.');

            return;
        }

        if (count($bookNumbers) > BundleService::MAX_BUNDLE_SIZE) {
            $this->addError('book_numbers', 'Ein Stapel-Angebot darf maximal '.BundleService::MAX_BUNDLE_SIZE.' Romane enthalten.');

            return;
        }

        $conditionError = $bundleService->validateConditionRange($this->condition, $this->condition_max);
        if ($conditionError) {
            $this->addError('condition_max', $conditionError);

            return;
        }

        $series = $this->isEditing
            ? BookOffer::where('bundle_id', $this->bundleId)->first()->series
            : $this->series;

        $existingBooks = $bundleService->getExistingBooks($series, $bookNumbers);
        $missingList = $bundleService->validateMissingBookNumbers($bookNumbers, $existingBooks->keys()->toArray());
        if ($missingList) {
            $this->addError('book_numbers', "Folgende Roman-Nummern existieren nicht in der Serie \"{$series}\": {$missingList}");

            return;
        }

        if ($this->isEditing) {
            $existingOffers = BookOffer::where('bundle_id', $this->bundleId)
                ->where('user_id', Auth::id())
                ->get();

            // Handle photo updates
            $currentPhotos = $existingOffers->first()->photos ?? [];
            $photosToKeep = collect($currentPhotos)
                ->reject(fn ($path) => in_array($path, $this->remove_photos))
                ->values()
                ->toArray();

            $photosToDelete = collect($this->remove_photos)
                ->filter(fn ($path) => in_array($path, $currentPhotos))
                ->values()
                ->toArray();

            $newPhotoPaths = [];
            if (! empty($this->photos)) {
                try {
                    $newPhotoPaths = $photoService->uploadPhotos(
                        array_filter($this->photos, fn ($p) => $p instanceof \Illuminate\Http\UploadedFile),
                    );
                } catch (\RuntimeException $e) {
                    $this->addError('photos', 'Foto-Upload fehlgeschlagen.');

                    return;
                }
            }

            $bundleService->updateBundle(
                $this->bundleId,
                $bookNumbers,
                $this->condition,
                $this->condition_max,
                array_merge($photosToKeep, $newPhotoPaths),
                $photosToDelete,
                Auth::id()
            );

            session()->flash('success', 'Stapel-Angebot aktualisiert.');
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

            try {
                $result = $bundleService->createBundle(
                    $this->series,
                    $bookNumbers,
                    $this->condition,
                    $this->condition_max,
                    $photoPaths,
                    Auth::id()
                );
            } catch (\RuntimeException $e) {
                $this->addError('book_numbers', $e->getMessage());

                return;
            }

            session()->flash('success', 'Stapel-Angebot mit '.count($result['offers']).' Romanen erstellt.');
        }

        $this->redirect(route('romantausch.index'));
    }

    public function placeholder()
    {
        return view('components.skeleton-form', ['fields' => 5]);
    }

    public function render()
    {
        return view('livewire.romantausch-bundle-form')
            ->layout('layouts.app', ['title' => $this->isEditing ? 'Stapel-Angebot bearbeiten' : 'Stapel-Angebot erstellen']);
    }
}
