<?php

namespace App\Livewire;

use App\Actions\Romantausch\BundleWorkflow;
use App\Http\Requests\StoreBookOfferRequest;
use App\Livewire\Concerns\InteractsWithRomantauschForm;
use App\Models\BookOffer;
use App\Services\Romantausch\BookPhotoService;
use App\Services\Romantausch\BundleService;
use App\Support\ConditionOptions;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class RomantauschBundleForm extends Component
{
    use InteractsWithRomantauschForm;
    use WithFileUploads;

    #[Locked]
    public ?string $bundleId = null;

    public string $series = '';

    public string $book_numbers = '';

    public string $condition = 'Z0';

    public ?string $condition_max = null;

    /** @var array<TemporaryUploadedFile> */
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
        $workflow = app(BundleWorkflow::class);
        $data = $this->only(['series', 'book_numbers', 'condition', 'condition_max', 'photos', 'remove_photos']);
        $this->submitRomantausch(
            fn () => $this->isEditing ? $workflow->update(Auth::user(), $this->bundleId, $data) : $workflow->create(Auth::user(), $data),
            $this->isEditing ? 'Stapel-Angebot aktualisiert.' : null,
        );
    }

    public function delete(): void
    {
        if ($this->bundleId) {
            app(BundleWorkflow::class)->delete(Auth::user(), $this->bundleId);
            session()->put('romantausch.success', 'Stapel-Angebot gelöscht.');
            $this->redirect(route('romantausch.index'));
        }
    }

    public function placeholder()
    {
        return view('components.skeleton-form', ['fields' => 5]);
    }

    public function render()
    {
        return view('livewire.romantausch-bundle-form')
            ->layout('layouts.member', ['title' => $this->isEditing ? 'Stapel-Angebot bearbeiten' : 'Stapel-Angebot erstellen']);
    }
}
