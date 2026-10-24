<?php

namespace App\Livewire;

use App\Models\BaxxEarningRule;
use App\Models\Download;
use App\Models\MaddraxikonRewardEvent;
use App\Models\MaddraxiversumBaxxSpecialOffer;
use App\Models\ReviewBaxxSpecialOffer;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\RomantauschBaxxSpecialOffer;
use App\Services\Baxx\BaxxSpecialOfferManager;
use App\Services\MaddraxiversumBaxxService;
use App\Services\ReviewBaxxService;
use App\Services\RewardService;
use App\Services\Romantausch\RomantauschBaxxService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class BelohnungenAdmin extends Component
{
    use WithFileUploads;

    private const PURCHASE_SORT_COLUMNS = [
        'cost_baxx' => 'cost_baxx',
        'purchased_at' => 'purchased_at',
        'refunded_at' => 'refunded_at',
    ];

    private const DOWNLOAD_SORT_COLUMNS = [
        'sort_order' => 'sort_order',
        'title' => 'title',
        'category' => 'category',
        'is_active' => 'is_active',
    ];

    #[Url(except: 'rewards')]
    public string $activeTab = 'rewards';

    // --- Reward editing ---
    public bool $showRewardModal = false;

    public ?int $editingRewardId = null;

    public string $rewardTitle = '';

    public string $rewardDescription = '';

    public string $rewardCategory = '';

    public int $rewardCostBaxx = 1;

    public int $rewardSortOrder = 0;

    public bool $rewardIsActive = true;

    // --- Purchase filter ---
    public string $purchaseSearch = '';

    public string $purchaseRewardFilter = 'alle';

    /** @var array{column: string, direction: string} */
    public array $purchaseSortBy = [
        'column' => 'purchased_at',
        'direction' => 'desc',
    ];

    // --- Earning rule editing ---
    public bool $showRuleModal = false;

    public ?int $editingRuleId = null;

    public string $ruleLabel = '';

    public string $ruleDescription = '';

    public int $rulePoints = 1;

    public int $ruleEveryCount = 1;

    public bool $ruleIsActive = true;

    // --- Review special offers ---
    public bool $showReviewSpecialOfferModal = false;

    public ?int $editingReviewSpecialOfferId = null;

    public int $reviewSpecialOfferPoints = 1;

    public int $reviewSpecialOfferEveryCount = 1;

    public string $reviewSpecialOfferEndsAt = '';

    public bool $reviewSpecialOfferIsActive = true;

    // --- Maddraxiversum special offers ---
    public bool $showMaddraxiversumSpecialOfferModal = false;

    public ?int $editingMaddraxiversumSpecialOfferId = null;

    public int $maddraxiversumSpecialOfferPoints = 1;

    public int $maddraxiversumSpecialOfferEveryCount = 1;

    public string $maddraxiversumSpecialOfferEndsAt = '';

    public bool $maddraxiversumSpecialOfferIsActive = true;

    // --- Romantausch special offers ---
    public bool $showRomantauschSpecialOfferModal = false;

    public ?int $editingRomantauschSpecialOfferId = null;

    public string $romantauschSpecialOfferActionKey = 'romantausch_offer';

    public int $romantauschSpecialOfferPoints = 1;

    public int $romantauschSpecialOfferEveryCount = 1;

    public string $romantauschSpecialOfferEndsAt = '';

    public bool $romantauschSpecialOfferIsActive = true;

    // --- Download management ---
    public bool $showDownloadModal = false;

    public ?int $editingDownloadId = null;

    public string $downloadTitle = '';

    public string $downloadDescription = '';

    public string $downloadCategory = '';

    public int $downloadSortOrder = 0;

    public bool $downloadIsActive = true;

    /** @var array{column: string, direction: string} */
    public array $downloadSortBy = [
        'column' => 'category',
        'direction' => 'asc',
    ];

    public $downloadFile = null;

    // --- Reward → Download link ---
    public ?int $rewardDownloadId = null;

    #[Computed]
    public function rewards(): \Illuminate\Database\Eloquent\Collection
    {
        return Reward::orderBy('sort_order')
            ->orderBy('cost_baxx')
            ->withCount(['activePurchases as purchase_count'])
            ->with(['threeDModel:id,name,reward_id', 'fanfiction:id,title,reward_id', 'download:id,title'])
            ->get();
    }

    #[Computed]
    public function mitgliederkarteReward(): Reward
    {
        return app(RewardService::class)->resolveMitgliederkarteReward();
    }

    #[Computed]
    public function earningRules(): \Illuminate\Database\Eloquent\Collection
    {
        return BaxxEarningRule::query()
            ->whereNotIn('action_key', $this->managedMaddraxikonActionKeys())
            ->orderBy('action_key')
            ->get();
    }

    #[Computed]
    public function reviewSpecialOffers(): \Illuminate\Database\Eloquent\Collection
    {
        return ReviewBaxxSpecialOffer::query()
            ->orderByDesc('is_active')
            ->orderByDesc('ends_at')
            ->get();
    }

    #[Computed]
    public function reviewRewardConfiguration(): array
    {
        $service = app(ReviewBaxxService::class);

        return [
            'base_rule' => $service->getBaseRuleData(),
            'effective_rule' => $service->getEffectiveRule(),
            'prominent_special_offer' => $service->getProminentSpecialOffer(),
        ];
    }

    #[Computed]
    public function maddraxiversumSpecialOffers(): \Illuminate\Database\Eloquent\Collection
    {
        return MaddraxiversumBaxxSpecialOffer::query()
            ->orderByDesc('is_active')
            ->orderByDesc('ends_at')
            ->get();
    }

    #[Computed]
    public function maddraxiversumRewardConfiguration(): array
    {
        $service = app(MaddraxiversumBaxxService::class);

        return [
            'base_rule' => $service->getBaseRuleData(),
            'effective_rule' => $service->getEffectiveRule(),
            'prominent_special_offer' => $service->getProminentSpecialOffer(),
        ];
    }

    #[Computed]
    public function romantauschSpecialOffers(): \Illuminate\Database\Eloquent\Collection
    {
        return RomantauschBaxxSpecialOffer::query()
            ->orderBy('action_key')
            ->orderByDesc('is_active')
            ->orderByDesc('ends_at')
            ->get();
    }

    #[Computed]
    public function romantauschRewardConfiguration(): array
    {
        return app(RomantauschBaxxService::class)->getActionConfigurations();
    }

    #[Computed]
    public function romantauschSpecialOfferActionOptions(): array
    {
        return RomantauschBaxxSpecialOffer::actionOptions();
    }

    #[Computed]
    public function purchases(): Collection
    {
        $sort = $this->normalizedSort(
            $this->purchaseSortBy,
            self::PURCHASE_SORT_COLUMNS,
            ['column' => 'purchased_at', 'direction' => 'desc'],
        );

        return $this->purchaseQuery()
            ->with(['user', 'reward', 'refundedByUser'])
            ->orderBy($sort['column'], $sort['direction'])
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function purchasesCount(): int
    {
        return $this->purchaseQuery()->count();
    }

    /**
     * @return Builder<RewardPurchase>
     */
    private function purchaseQuery(): Builder
    {
        $query = RewardPurchase::query();

        if ($this->purchaseSearch !== '') {
            $search = $this->purchaseSearch;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($this->purchaseRewardFilter !== 'alle') {
            $query->where('reward_id', $this->purchaseRewardFilter);
        }

        return $query;
    }

    #[Computed]
    public function statistics(): array
    {
        return app(RewardService::class)->getAdminStatistics();
    }

    #[Computed]
    public function categories(): array
    {
        return Reward::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->toArray();
    }

    #[Computed]
    public function downloads(): \Illuminate\Database\Eloquent\Collection
    {
        $sort = $this->normalizedSort(
            $this->downloadSortBy,
            self::DOWNLOAD_SORT_COLUMNS,
            ['column' => 'category', 'direction' => 'asc'],
        );

        return Download::with(['reward:id,title,download_id'])
            ->orderBy($sort['column'], $sort['direction'])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array{column?: mixed, direction?: mixed}  $sort
     * @param  array<string, string>  $allowedColumns
     * @param  array{column: string, direction: 'asc'|'desc'}  $default
     * @return array{column: string, direction: 'asc'|'desc'}
     */
    private function normalizedSort(array $sort, array $allowedColumns, array $default): array
    {
        $requestedColumn = is_string($sort['column'] ?? null)
            ? $sort['column']
            : $default['column'];
        $requestedDirection = is_string($sort['direction'] ?? null)
            ? strtolower($sort['direction'])
            : $default['direction'];

        return [
            'column' => $allowedColumns[$requestedColumn] ?? $allowedColumns[$default['column']],
            'direction' => in_array($requestedDirection, ['asc', 'desc'], true)
                ? $requestedDirection
                : $default['direction'],
        ];
    }

    #[Computed]
    public function downloadCategories(): array
    {
        return Download::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->toArray();
    }

    /**
     * Downloads, die im Reward-Modal als Optionen angeboten werden:
     * nur solche ohne bestehende Reward-Verknüpfung
     * + der aktuell verknüpfte Download beim Bearbeiten.
     */
    #[Computed]
    public function availableDownloadsForReward(): \Illuminate\Database\Eloquent\Collection
    {
        return Download::where(function ($query) {
            $query->whereDoesntHave('reward');

            if ($this->rewardDownloadId) {
                $query->orWhere('id', $this->rewardDownloadId);
            }
        })
            ->orderBy('title')
            ->get(['id', 'title', 'category']);
    }

    /**
     * @return array{rewards: int, rules: int, purchases: int, statistics: int, downloads: int}
     */
    public function tabBadges(): array
    {
        $rewardsCount = Reward::query()->count();
        $specialOffersCount = ReviewBaxxSpecialOffer::query()->count()
            + MaddraxiversumBaxxSpecialOffer::query()->count()
            + RomantauschBaxxSpecialOffer::query()->count();

        return [
            'rewards' => $rewardsCount,
            'rules' => BaxxEarningRule::query()->count() + $specialOffersCount,
            'purchases' => $this->purchasesCount,
            'statistics' => $rewardsCount,
            'downloads' => Download::query()->count(),
        ];
    }

    // =====================================================
    // Reward CRUD
    // =====================================================

    public function openCreateReward(): void
    {
        $this->resetRewardForm();
        $this->showRewardModal = true;
    }

    public function openEditReward(int $rewardId): void
    {
        $reward = Reward::findOrFail($rewardId);
        $this->editingRewardId = $reward->id;
        $this->rewardTitle = $reward->title;
        $this->rewardDescription = $reward->description;
        $this->rewardCategory = $reward->category;
        $this->rewardCostBaxx = $reward->cost_baxx;
        $this->rewardSortOrder = $reward->sort_order;
        $this->rewardIsActive = $reward->is_active;
        $this->rewardDownloadId = $reward->download_id;
        $this->showRewardModal = true;
    }

    public function openEditMitgliederkarteReward(): void
    {
        $this->openEditReward(app(RewardService::class)->resolveMitgliederkarteReward()->id);
    }

    public function saveReward(): void
    {
        $this->validate([
            'rewardTitle' => 'required|string|max:255',
            'rewardDescription' => 'required|string',
            'rewardCategory' => 'required|string|max:255',
            'rewardCostBaxx' => 'required|integer|min:1',
            'rewardSortOrder' => 'required|integer|min:0',
            'rewardDownloadId' => [
                'nullable',
                'exists:downloads,id',
                Rule::unique('rewards', 'download_id')->ignore($this->editingRewardId),
            ],
        ]);

        $data = [
            'title' => $this->rewardTitle,
            'description' => $this->rewardDescription,
            'category' => $this->rewardCategory,
            'cost_baxx' => $this->rewardCostBaxx,
            'sort_order' => $this->rewardSortOrder,
            'is_active' => $this->rewardIsActive,
            'download_id' => $this->rewardDownloadId,
        ];

        if ($this->editingRewardId) {
            $reward = Reward::findOrFail($this->editingRewardId);
            $reward->update($data);
            $this->dispatch('toast', type: 'success', title: 'Belohnung aktualisiert');
        } else {
            // Slug wird automatisch im Model::booted() erzeugt (mit Kollisionserkennung)
            Reward::create($data);
            $this->dispatch('toast', type: 'success', title: 'Belohnung erstellt');
        }

        $this->showRewardModal = false;
        $this->resetRewardForm();
        unset($this->rewards, $this->mitgliederkarteReward, $this->statistics);
    }

    public function toggleRewardActive(int $rewardId): void
    {
        $reward = Reward::findOrFail($rewardId);
        $reward->update(['is_active' => ! $reward->is_active]);
        $status = $reward->is_active ? 'aktiviert' : 'deaktiviert';
        $this->dispatch('toast', type: 'success', title: "Belohnung {$status}");
        unset($this->rewards, $this->mitgliederkarteReward, $this->statistics);
    }

    private function resetRewardForm(): void
    {
        $this->editingRewardId = null;
        $this->rewardTitle = '';
        $this->rewardDescription = '';
        $this->rewardCategory = '';
        $this->rewardCostBaxx = 1;
        $this->rewardSortOrder = 0;
        $this->rewardIsActive = true;
        $this->rewardDownloadId = null;
    }

    // =====================================================
    // Earning Rules
    // =====================================================

    public function openEditRule(int $ruleId): void
    {
        $rule = BaxxEarningRule::query()
            ->whereNotIn('action_key', $this->managedMaddraxikonActionKeys())
            ->findOrFail($ruleId);
        $this->editingRuleId = $rule->id;
        $this->ruleLabel = $rule->label;
        $this->ruleDescription = $rule->description ?? '';
        $this->rulePoints = $rule->points;
        $this->ruleEveryCount = $rule->every_count;
        $this->ruleIsActive = $rule->is_active;
        $this->showRuleModal = true;
    }

    public function saveRule(): void
    {
        $this->validate([
            'ruleLabel' => 'required|string|max:255',
            'rulePoints' => 'required|integer|min:0',
            'ruleEveryCount' => 'required|integer|min:1',
        ]);

        if ($this->editingRuleId) {
            $rule = BaxxEarningRule::query()
                ->whereNotIn('action_key', $this->managedMaddraxikonActionKeys())
                ->findOrFail($this->editingRuleId);
            $rule->update([
                'label' => $this->ruleLabel,
                'description' => $this->ruleDescription ?: null,
                'points' => $this->rulePoints,
                'every_count' => $this->ruleEveryCount,
                'is_active' => $this->ruleIsActive,
            ]);
            $this->dispatch('toast', type: 'success', title: 'Vergaberegel aktualisiert');
        }

        $this->showRuleModal = false;
        $this->editingRuleId = null;
        $this->ruleEveryCount = 1;
        unset(
            $this->earningRules,
            $this->reviewRewardConfiguration,
            $this->maddraxiversumRewardConfiguration,
            $this->romantauschRewardConfiguration,
        );
    }

    public function toggleRuleActive(int $ruleId): void
    {
        $rule = BaxxEarningRule::query()
            ->whereNotIn('action_key', $this->managedMaddraxikonActionKeys())
            ->findOrFail($ruleId);
        $rule->update(['is_active' => ! $rule->is_active]);
        $status = $rule->is_active ? 'aktiviert' : 'deaktiviert';
        $this->dispatch('toast', type: 'success', title: "Vergaberegel {$status}");
        unset(
            $this->earningRules,
            $this->reviewRewardConfiguration,
            $this->maddraxiversumRewardConfiguration,
            $this->romantauschRewardConfiguration,
        );
    }

    /** @return list<string> */
    private function managedMaddraxikonActionKeys(): array
    {
        return [
            MaddraxikonRewardEvent::ACTION_EDIT_SESSION,
            MaddraxikonRewardEvent::ACTION_NEW_ARTICLE,
        ];
    }

    public function openCreateReviewSpecialOffer(): void
    {
        $this->resetReviewSpecialOfferForm();
        $this->showReviewSpecialOfferModal = true;
    }

    public function openEditReviewSpecialOffer(int $offerId): void
    {
        $offer = ReviewBaxxSpecialOffer::findOrFail($offerId);
        $this->editingReviewSpecialOfferId = $offer->id;
        $this->reviewSpecialOfferPoints = $offer->points;
        $this->reviewSpecialOfferEveryCount = $offer->every_count;
        $this->reviewSpecialOfferEndsAt = $offer->ends_at->copy()->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
        $this->reviewSpecialOfferIsActive = $offer->is_active;
        $this->showReviewSpecialOfferModal = true;
    }

    public function saveReviewSpecialOffer(): void
    {
        $this->validate([
            'reviewSpecialOfferPoints' => 'required|integer|min:1',
            'reviewSpecialOfferEveryCount' => 'required|integer|min:1',
            'reviewSpecialOfferEndsAt' => 'required|date',
        ]);

        app(BaxxSpecialOfferManager::class)->save(auth()->user(), 'rezension', [
            'points' => $this->reviewSpecialOfferPoints,
            'every_count' => $this->reviewSpecialOfferEveryCount,
            'ends_at' => $this->reviewSpecialOfferEndsAt,
            'is_active' => $this->reviewSpecialOfferIsActive,
        ], $this->editingReviewSpecialOfferId, 'reviewSpecialOffer');

        $title = $this->editingReviewSpecialOfferId ? 'Review-Sonderaktion aktualisiert' : 'Review-Sonderaktion erstellt';
        $this->dispatch('toast', type: 'success', title: $title);
        $this->showReviewSpecialOfferModal = false;
        $this->resetReviewSpecialOfferForm();
        unset($this->reviewSpecialOffers, $this->reviewRewardConfiguration);
    }

    public function toggleReviewSpecialOfferActive(int $offerId): void
    {
        $status = app(BaxxSpecialOfferManager::class)->toggle(auth()->user(), 'rezension', $offerId, 'reviewSpecialOffer');
        $this->dispatch('toast', type: 'success', title: "Review-Sonderaktion {$status}");
        unset($this->reviewSpecialOffers, $this->reviewRewardConfiguration);
    }

    /**
     * @return array{icon: string, tooltip: string, disabled: bool}
     */
    public function specialOfferToggleAction(bool $isActive, Carbon $endsAt): array
    {
        if ($isActive) {
            return [
                'icon' => 'o-eye-slash',
                'tooltip' => 'Deaktivieren',
                'disabled' => false,
            ];
        }

        if ($endsAt->isFuture()) {
            return [
                'icon' => 'o-eye',
                'tooltip' => 'Aktivieren',
                'disabled' => false,
            ];
        }

        return [
            'icon' => 'o-clock',
            'tooltip' => 'Abgelaufen und nicht erneut aktivierbar',
            'disabled' => true,
        ];
    }

    private function resetReviewSpecialOfferForm(): void
    {
        $this->editingReviewSpecialOfferId = null;
        $this->reviewSpecialOfferPoints = 1;
        $this->reviewSpecialOfferEveryCount = 1;
        $this->reviewSpecialOfferEndsAt = now()->addDay()->format('Y-m-d\TH:i');
        $this->reviewSpecialOfferIsActive = true;
    }

    public function openCreateMaddraxiversumSpecialOffer(): void
    {
        $this->resetMaddraxiversumSpecialOfferForm();
        $this->showMaddraxiversumSpecialOfferModal = true;
    }

    public function openEditMaddraxiversumSpecialOffer(int $offerId): void
    {
        $offer = MaddraxiversumBaxxSpecialOffer::findOrFail($offerId);
        $this->editingMaddraxiversumSpecialOfferId = $offer->id;
        $this->maddraxiversumSpecialOfferPoints = $offer->points;
        $this->maddraxiversumSpecialOfferEveryCount = $offer->every_count;
        $this->maddraxiversumSpecialOfferEndsAt = $offer->ends_at->copy()->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
        $this->maddraxiversumSpecialOfferIsActive = $offer->is_active;
        $this->showMaddraxiversumSpecialOfferModal = true;
    }

    public function saveMaddraxiversumSpecialOffer(): void
    {
        $this->validate([
            'maddraxiversumSpecialOfferPoints' => 'required|integer|min:1',
            'maddraxiversumSpecialOfferEveryCount' => 'required|integer|min:1',
            'maddraxiversumSpecialOfferEndsAt' => 'required|date',
        ]);

        app(BaxxSpecialOfferManager::class)->save(auth()->user(), 'maddraxiversum_mission', [
            'points' => $this->maddraxiversumSpecialOfferPoints,
            'every_count' => $this->maddraxiversumSpecialOfferEveryCount,
            'ends_at' => $this->maddraxiversumSpecialOfferEndsAt,
            'is_active' => $this->maddraxiversumSpecialOfferIsActive,
        ], $this->editingMaddraxiversumSpecialOfferId, 'maddraxiversumSpecialOffer');

        $title = $this->editingMaddraxiversumSpecialOfferId ? 'Maddraxiversum-Sonderaktion aktualisiert' : 'Maddraxiversum-Sonderaktion erstellt';
        $this->dispatch('toast', type: 'success', title: $title);
        $this->showMaddraxiversumSpecialOfferModal = false;
        $this->resetMaddraxiversumSpecialOfferForm();
        unset($this->maddraxiversumSpecialOffers, $this->maddraxiversumRewardConfiguration);
    }

    public function toggleMaddraxiversumSpecialOfferActive(int $offerId): void
    {
        $status = app(BaxxSpecialOfferManager::class)->toggle(auth()->user(), 'maddraxiversum_mission', $offerId, 'maddraxiversumSpecialOffer');
        $this->dispatch('toast', type: 'success', title: "Maddraxiversum-Sonderaktion {$status}");
        unset($this->maddraxiversumSpecialOffers, $this->maddraxiversumRewardConfiguration);
    }

    private function resetMaddraxiversumSpecialOfferForm(): void
    {
        $this->editingMaddraxiversumSpecialOfferId = null;
        $this->maddraxiversumSpecialOfferPoints = 1;
        $this->maddraxiversumSpecialOfferEveryCount = 1;
        $this->maddraxiversumSpecialOfferEndsAt = now()->addDay()->format('Y-m-d\TH:i');
        $this->maddraxiversumSpecialOfferIsActive = true;
    }

    public function openCreateRomantauschSpecialOffer(): void
    {
        $this->resetRomantauschSpecialOfferForm();
        $this->showRomantauschSpecialOfferModal = true;
    }

    public function openEditRomantauschSpecialOffer(int $offerId): void
    {
        $offer = RomantauschBaxxSpecialOffer::findOrFail($offerId);
        $this->editingRomantauschSpecialOfferId = $offer->id;
        $this->romantauschSpecialOfferActionKey = $offer->action_key;
        $this->romantauschSpecialOfferPoints = $offer->points;
        $this->romantauschSpecialOfferEveryCount = $offer->every_count;
        $this->romantauschSpecialOfferEndsAt = $offer->ends_at->copy()->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
        $this->romantauschSpecialOfferIsActive = $offer->is_active;
        $this->showRomantauschSpecialOfferModal = true;
    }

    public function saveRomantauschSpecialOffer(): void
    {
        $this->validate([
            'romantauschSpecialOfferActionKey' => ['required', Rule::in(RomantauschBaxxSpecialOffer::allowedActionKeys())],
            'romantauschSpecialOfferPoints' => 'required|integer|min:1',
            'romantauschSpecialOfferEveryCount' => 'required|integer|min:1',
            'romantauschSpecialOfferEndsAt' => 'required|date',
        ]);

        app(BaxxSpecialOfferManager::class)->save(auth()->user(), $this->romantauschSpecialOfferActionKey, [
            'points' => $this->romantauschSpecialOfferPoints,
            'every_count' => $this->romantauschSpecialOfferEveryCount,
            'ends_at' => $this->romantauschSpecialOfferEndsAt,
            'is_active' => $this->romantauschSpecialOfferIsActive,
        ], $this->editingRomantauschSpecialOfferId, 'romantauschSpecialOffer');

        $title = $this->editingRomantauschSpecialOfferId ? 'Romantausch-Sonderaktion aktualisiert' : 'Romantausch-Sonderaktion erstellt';
        $this->dispatch('toast', type: 'success', title: $title);
        $this->showRomantauschSpecialOfferModal = false;
        $this->resetRomantauschSpecialOfferForm();
        unset($this->romantauschSpecialOffers, $this->romantauschRewardConfiguration);
    }

    public function toggleRomantauschSpecialOfferActive(int $offerId): void
    {
        $actionKey = RomantauschBaxxSpecialOffer::findOrFail($offerId)->action_key;
        $status = app(BaxxSpecialOfferManager::class)->toggle(auth()->user(), $actionKey, $offerId, 'romantauschSpecialOffer');
        $this->dispatch('toast', type: 'success', title: "Romantausch-Sonderaktion {$status}");
        unset($this->romantauschSpecialOffers, $this->romantauschRewardConfiguration);
    }

    private function resetRomantauschSpecialOfferForm(): void
    {
        $this->editingRomantauschSpecialOfferId = null;
        $this->romantauschSpecialOfferActionKey = RomantauschBaxxSpecialOffer::allowedActionKeys()[0];
        $this->romantauschSpecialOfferPoints = 1;
        $this->romantauschSpecialOfferEveryCount = 1;
        $this->romantauschSpecialOfferEndsAt = now()->addDay()->format('Y-m-d\TH:i');
        $this->romantauschSpecialOfferIsActive = true;
    }

    // =====================================================
    // Purchase Refunds
    // =====================================================

    public function refundPurchase(int $purchaseId): void
    {
        $purchase = RewardPurchase::with(['user', 'reward'])->findOrFail($purchaseId);
        $service = app(RewardService::class);

        try {
            $service->refundPurchase($purchase, Auth::user());
            $this->dispatch('toast', type: 'success', title: 'Erstattung durchgeführt', description: e($purchase->user->name).' erhält '.$purchase->cost_baxx.' Baxx zurück.');
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', type: 'error', title: 'Fehler', description: $message);
        }

        unset($this->purchases, $this->purchasesCount, $this->statistics, $this->rewards);
    }

    public function updatedPurchaseSearch(): void
    {
        unset($this->purchases, $this->purchasesCount);
    }

    public function updatedPurchaseRewardFilter(): void
    {
        unset($this->purchases, $this->purchasesCount);
    }

    // =====================================================
    // Download CRUD
    // =====================================================

    public function openCreateDownload(): void
    {
        $this->resetDownloadForm();
        $this->showDownloadModal = true;
    }

    public function openEditDownload(int $downloadId): void
    {
        $download = Download::findOrFail($downloadId);
        $this->editingDownloadId = $download->id;
        $this->downloadTitle = $download->title;
        $this->downloadDescription = $download->description ?? '';
        $this->downloadCategory = $download->category;
        $this->downloadSortOrder = $download->sort_order;
        $this->downloadIsActive = $download->is_active;
        $this->downloadFile = null;
        $this->showDownloadModal = true;
    }

    public function saveDownload(): void
    {
        $rules = [
            'downloadTitle' => 'required|string|max:255',
            'downloadCategory' => 'required|string|max:255',
            'downloadSortOrder' => 'required|integer|min:0',
        ];

        if (! $this->editingDownloadId) {
            $rules['downloadFile'] = 'required|file|mimes:pdf,zip,epub|max:51200';
        } else {
            $rules['downloadFile'] = 'nullable|file|mimes:pdf,zip,epub|max:51200';
        }

        $this->validate($rules);

        $data = [
            'title' => $this->downloadTitle,
            'description' => $this->downloadDescription ?: null,
            'category' => $this->downloadCategory,
            'sort_order' => $this->downloadSortOrder,
            'is_active' => $this->downloadIsActive,
        ];

        $newFilePath = null;
        $oldFilePath = null;

        if ($this->downloadFile) {
            $originalFilename = $this->sanitizeFilename($this->downloadFile->getClientOriginalName());
            $newFilePath = $this->downloadFile->store('downloads', 'private');

            $data['file_path'] = $newFilePath;
            $data['original_filename'] = $originalFilename;
            $data['mime_type'] = $this->downloadFile->getMimeType();
            $data['file_size'] = $this->downloadFile->getSize();

            // Alte Datei merken, wird erst nach erfolgreichem DB-Update gelöscht
            if ($this->editingDownloadId) {
                $existing = Download::find($this->editingDownloadId);
                if ($existing) {
                    $oldFilePath = $existing->file_path;
                }
            }
        }

        try {
            DB::transaction(function () use ($data) {
                if ($this->editingDownloadId) {
                    $download = Download::findOrFail($this->editingDownloadId);
                    $download->update($data);
                } else {
                    Download::create($data);
                }
            });

            // DB-Update erfolgreich – jetzt alte Datei löschen
            if ($oldFilePath && $oldFilePath !== ($data['file_path'] ?? null)
                && Storage::disk('private')->exists($oldFilePath)) {
                Storage::disk('private')->delete($oldFilePath);
            }

            $this->dispatch('toast', type: 'success', title: $this->editingDownloadId ? 'Download aktualisiert' : 'Download erstellt');
        } catch (\Throwable $e) {
            // DB-Update fehlgeschlagen – neue Datei aufräumen
            if ($newFilePath && Storage::disk('private')->exists($newFilePath)) {
                Storage::disk('private')->delete($newFilePath);
            }

            throw $e;
        }

        $this->showDownloadModal = false;
        $this->resetDownloadForm();
        unset($this->downloads);
    }

    public function toggleDownloadActive(int $downloadId): void
    {
        $download = Download::findOrFail($downloadId);
        $download->update(['is_active' => ! $download->is_active]);
        $status = $download->is_active ? 'aktiviert' : 'deaktiviert';
        $this->dispatch('toast', type: 'success', title: "Download {$status}");
        unset($this->downloads);
    }

    public function deleteDownload(int $downloadId): void
    {
        $download = Download::findOrFail($downloadId);

        // Prüfen ob eine aktive Belohnung oder aktive Käufe mit diesem Download verknüpft sind
        $hasActiveReward = $download->reward()
            ->where('is_active', true)
            ->exists();

        if ($hasActiveReward) {
            $this->dispatch('toast', type: 'error', title: 'Löschen nicht möglich', description: 'Dieser Download ist mit einer aktiven Belohnung verknüpft. Bitte zuerst die Belohnung deaktivieren oder die Verknüpfung entfernen.');

            return;
        }

        $hasActivePurchases = RewardPurchase::active()
            ->whereHas('reward', fn ($q) => $q->where('download_id', $download->id))
            ->exists();

        if ($hasActivePurchases) {
            $this->dispatch('toast', type: 'error', title: 'Löschen nicht möglich', description: 'Dieser Download ist mit Belohnungen verknüpft, die aktive Freischaltungen haben.');

            return;
        }

        $filePath = $download->file_path;

        // Zuerst DB löschen, dann Datei entfernen
        $download->delete();

        if (Storage::disk('private')->exists($filePath)) {
            Storage::disk('private')->delete($filePath);
        }

        $this->dispatch('toast', type: 'success', title: 'Download gelöscht');
        unset($this->downloads, $this->rewards);
    }

    private function resetDownloadForm(): void
    {
        $this->editingDownloadId = null;
        $this->downloadTitle = '';
        $this->downloadDescription = '';
        $this->downloadCategory = '';
        $this->downloadSortOrder = 0;
        $this->downloadIsActive = true;
        $this->downloadFile = null;
    }

    /**
     * Bereinigt einen vom Client übermittelten Dateinamen.
     * Entfernt Pfadbestandteile, Steuerzeichen und kürzt auf max. 255 Zeichen.
     */
    private function sanitizeFilename(string $filename): string
    {
        // Nur den Dateinamen ohne Pfad verwenden
        $filename = basename($filename);

        // Steuerzeichen entfernen (ASCII 0-31 und 127)
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename);

        // Mehrfache Leerzeichen/Punkte normalisieren
        $filename = preg_replace('/\s+/', ' ', $filename);

        // Auf 255 Zeichen kürzen (Dateisystem-Limit)
        if (mb_strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $maxNameLength = 255 - mb_strlen($extension) - 1;
            $filename = mb_substr($name, 0, $maxNameLength).'.'.$extension;
        }

        return $filename ?: 'download';
    }

    public function placeholder()
    {
        return view('components.skeleton-table', ['columns' => 5, 'rows' => 8]);
    }

    public function render()
    {
        $view = app()->runningUnitTests() && config('app.testing_minimal_belohnungen_admin', false)
            ? 'testing.livewire.belohnungen-admin'
            : 'livewire.belohnungen-admin';

        return view($view)
            ->layout('layouts.member', ['title' => 'Belohnungen - Admin']);
    }
}
