<?php

namespace App\Livewire;

use App\Models\BaxxEarningRule;
use App\Models\Download;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Services\RewardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class BelohnungenAdmin extends Component
{
    use WithFileUploads;

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

    // --- Earning rule editing ---
    public bool $showRuleModal = false;

    public ?int $editingRuleId = null;

    public string $ruleLabel = '';

    public string $ruleDescription = '';

    public int $rulePoints = 1;

    public bool $ruleIsActive = true;

    // --- Download management ---
    public bool $showDownloadModal = false;

    public ?int $editingDownloadId = null;

    public string $downloadTitle = '';

    public string $downloadDescription = '';

    public string $downloadCategory = '';

    public int $downloadSortOrder = 0;

    public bool $downloadIsActive = true;

    public $downloadFile = null;

    // --- Reward → Download link ---
    public ?int $rewardDownloadId = null;

    #[Computed]
    public function rewards(): \Illuminate\Database\Eloquent\Collection
    {
        return Reward::orderBy('sort_order')
            ->orderBy('cost_baxx')
            ->withCount(['activePurchases as purchase_count'])
            ->get();
    }

    #[Computed]
    public function earningRules(): \Illuminate\Database\Eloquent\Collection
    {
        return BaxxEarningRule::orderBy('action_key')->get();
    }

    #[Computed]
    public function purchases(): \Illuminate\Support\Collection
    {
        $query = RewardPurchase::with(['user', 'reward', 'refundedByUser'])
            ->latest('purchased_at');

        if ($this->purchaseSearch !== '') {
            $search = $this->purchaseSearch;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($this->purchaseRewardFilter !== 'alle') {
            $query->where('reward_id', $this->purchaseRewardFilter);
        }

        return $query->limit(100)->get();
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
        return Download::with(['reward:id,title,download_id'])
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
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
        unset($this->rewards, $this->statistics);
    }

    public function toggleRewardActive(int $rewardId): void
    {
        $reward = Reward::findOrFail($rewardId);
        $reward->update(['is_active' => ! $reward->is_active]);
        $status = $reward->is_active ? 'aktiviert' : 'deaktiviert';
        $this->dispatch('toast', type: 'success', title: "Belohnung {$status}");
        unset($this->rewards, $this->statistics);
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
        $rule = BaxxEarningRule::findOrFail($ruleId);
        $this->editingRuleId = $rule->id;
        $this->ruleLabel = $rule->label;
        $this->ruleDescription = $rule->description ?? '';
        $this->rulePoints = $rule->points;
        $this->ruleIsActive = $rule->is_active;
        $this->showRuleModal = true;
    }

    public function saveRule(): void
    {
        $this->validate([
            'ruleLabel' => 'required|string|max:255',
            'rulePoints' => 'required|integer|min:0',
        ]);

        if ($this->editingRuleId) {
            $rule = BaxxEarningRule::findOrFail($this->editingRuleId);
            $rule->update([
                'label' => $this->ruleLabel,
                'description' => $this->ruleDescription ?: null,
                'points' => $this->rulePoints,
                'is_active' => $this->ruleIsActive,
            ]);
            $this->dispatch('toast', type: 'success', title: 'Vergaberegel aktualisiert');
        }

        $this->showRuleModal = false;
        $this->editingRuleId = null;
        unset($this->earningRules);
    }

    public function toggleRuleActive(int $ruleId): void
    {
        $rule = BaxxEarningRule::findOrFail($ruleId);
        $rule->update(['is_active' => ! $rule->is_active]);
        $status = $rule->is_active ? 'aktiviert' : 'deaktiviert';
        $this->dispatch('toast', type: 'success', title: "Vergaberegel {$status}");
        unset($this->earningRules);
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', type: 'error', title: 'Fehler', description: $message);
        }

        unset($this->purchases, $this->statistics, $this->rewards);
    }

    public function updatedPurchaseSearch(): void
    {
        unset($this->purchases);
    }

    public function updatedPurchaseRewardFilter(): void
    {
        unset($this->purchases);
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

        if ($this->downloadFile) {
            $originalFilename = $this->downloadFile->getClientOriginalName();
            $path = $this->downloadFile->store('downloads', 'private');

            $data['file_path'] = $path;
            $data['original_filename'] = $originalFilename;
            $data['mime_type'] = $this->downloadFile->getMimeType();
            $data['file_size'] = $this->downloadFile->getSize();

            // Delete old file when replacing
            if ($this->editingDownloadId) {
                $existing = Download::find($this->editingDownloadId);
                if ($existing && Storage::disk('private')->exists($existing->file_path)) {
                    Storage::disk('private')->delete($existing->file_path);
                }
            }
        }

        if ($this->editingDownloadId) {
            $download = Download::findOrFail($this->editingDownloadId);
            $download->update($data);
            $this->dispatch('toast', type: 'success', title: 'Download aktualisiert');
        } else {
            Download::create($data);
            $this->dispatch('toast', type: 'success', title: 'Download erstellt');
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

        // Check if any rewards reference this download with active purchases
        $hasActivePurchases = RewardPurchase::active()
            ->whereHas('reward', fn ($q) => $q->where('download_id', $download->id))
            ->exists();

        if ($hasActivePurchases) {
            $this->dispatch('toast', type: 'error', title: 'Löschen nicht möglich', description: 'Dieser Download ist mit Belohnungen verknüpft, die aktive Freischaltungen haben.');

            return;
        }

        // Delete the file from storage
        if (Storage::disk('private')->exists($download->file_path)) {
            Storage::disk('private')->delete($download->file_path);
        }

        $download->delete();
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

    public function render()
    {
        return view('livewire.belohnungen-admin')
            ->layout('layouts.app', ['title' => 'Belohnungen - Admin']);
    }
}
