<?php

namespace App\Livewire;

use App\Enums\BookType;
use App\Models\Reward;
use App\Services\BildersucheAccessService;
use App\Services\BildersucheService;
use App\Services\RewardService;
use App\Support\CoverRatings\CoverKeywords;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Bildersuche extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Url(except: 'all')]
    public string $series = 'all';

    #[Locked]
    public bool $hasSearched = false;

    #[Locked]
    public ?int $previewId = null;

    public function mount(): void
    {
        $access = app(BildersucheAccessService::class);
        $user = $access->ensureMemberAccess();
        if (trim($this->query) !== '' && $access->hasAccess($user)) {
            try {
                app(BildersucheService::class)->validateInput($this->query, $this->series, $this->getPage());
                $this->hasSearched = true;
            } catch (ValidationException $exception) {
                $this->setErrorBag($exception->errors());
            }
        }
    }

    public function performSearch(): void
    {
        app(BildersucheAccessService::class)->ensureSearchAccess();
        $this->hasSearched = false;
        $this->previewId = null;
        $this->resetPage();
        $this->resetValidation();
        app(BildersucheService::class)->validateInput($this->query, $this->series);
        $this->hasSearched = true;
    }

    public function updatedSeries(): void
    {
        app(BildersucheAccessService::class)->ensureSearchAccess();
        $this->resetPage();
        if ($this->hasSearched) {
            $this->performSearch();
        }
    }

    public function updatingPaginators(): void
    {
        app(BildersucheAccessService::class)->ensureSearchAccess();
        $this->previewId = null;
    }

    public function showCover(int $coverId): void
    {
        app(BildersucheAccessService::class)->ensureSearchAccess();
        app(BildersucheService::class)->searchable()->findOrFail($coverId);
        $this->previewId = $coverId;
    }

    public function closePreview(): void
    {
        $this->previewId = null;
    }

    public function purchase(): void
    {
        $user = app(BildersucheAccessService::class)->ensureMemberAccess();
        $reward = Reward::query()->where('slug', 'bildersuche')->firstOrFail();
        app(RewardService::class)->purchaseReward($user, $reward);
        $this->resetValidation();
    }

    public function render()
    {
        $access = app(BildersucheAccessService::class);
        $user = $access->ensureMemberAccess();
        $hasAccess = $access->hasAccess($user);
        $service = app(BildersucheService::class);
        $results = null;
        if ($hasAccess && $this->hasSearched) {
            try {
                $results = $service->search($this->query, $this->series, $this->getPage());
            } catch (ValidationException $exception) {
                $this->setErrorBag($exception->errors());
            }
        }
        $preview = $hasAccess && $this->previewId
            ? $service->searchable()->with(['book', 'keywords'])->find($this->previewId) : null;
        // Construct the link from our trusted wiki origin and the stored file title.
        $sourceUrl = $preview ? rtrim((string) config('maddraxikon.base_url'), '/').'/index.php?'.http_build_query([
            'title' => CoverKeywords::fileTitle($preview->source_file_title),
        ]) : null;

        return view('livewire.bildersuche', [
            'hasAccess' => $hasAccess,
            'reward' => Reward::query()->where('slug', 'bildersuche')->first(),
            'wallet' => $hasAccess ? null : app(RewardService::class)->getWalletState($user),
            'seriesOptions' => BookType::options(true),
            'searchableCount' => $hasAccess ? $service->searchable()->count() : null,
            'results' => $results,
            'preview' => $preview,
            'sourceUrl' => $sourceUrl,
        ])->layout('layouts.member', ['title' => 'Maddrax-Bildersuche']);
    }
}
