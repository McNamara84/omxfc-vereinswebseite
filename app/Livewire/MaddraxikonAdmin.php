<?php

namespace App\Livewire;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonRewardEventStatus;
use App\Enums\Role;
use App\Jobs\EvaluateMaddraxikonContributions;
use App\Jobs\SyncMaddraxikonContributions;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonAccountLinkCorrection;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardEvent;
use App\Models\MaddraxikonSyncState;
use App\Models\User;
use App\Services\Maddraxikon\AccountLinkService;
use App\Services\Maddraxikon\MaddraxikonNamespaceHealthService;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Component;
use LogicException;
use Throwable;

final class MaddraxikonAdmin extends Component
{
    public bool $showReversalModal = false;

    public ?int $reversingRewardEventId = null;

    public string $reversalReason = '';

    public bool $showLinkCorrectionModal = false;

    public ?int $correctingAccountLinkId = null;

    public string $linkCorrectionReason = '';

    public string $linkStatusFilter = 'all';

    public string $contributionStatusFilter = 'all';

    public string $rewardStatusFilter = 'all';

    /**
     * The namespace result is populated only by checkNamespaces(). Keeping it
     * as component state prevents a remote MediaWiki request during render().
     *
     * @var array<string, mixed>|null
     */
    public ?array $namespaceHealth = null;

    public ?string $namespaceHealthError = null;

    public function boot(): void
    {
        $this->adminUser();
    }

    public function dispatchSync(): void
    {
        $this->adminUser();

        if (! config('maddraxikon.features.sync_enabled', false)) {
            $this->dispatch(
                'toast',
                type: 'warning',
                title: 'Synchronisation ist deaktiviert',
            );

            return;
        }

        SyncMaddraxikonContributions::dispatch();

        $this->dispatch(
            'toast',
            type: 'success',
            title: 'Maddraxikon-Synchronisation eingereiht',
        );
    }

    public function dispatchEvaluation(): void
    {
        $this->adminUser();

        if (! config('maddraxikon.features.awards_enabled', false)) {
            $this->dispatch(
                'toast',
                type: 'warning',
                title: 'Baxx-Auswertung ist deaktiviert',
            );

            return;
        }

        $recoveryOpen = MaddraxikonSyncState::query()
            ->where(
                'wiki_key',
                (string) config('maddraxikon.wiki_key', 'maddraxikon-de'),
            )
            ->whereNotNull('recovery_required_at')
            ->exists();

        if ($recoveryOpen) {
            $this->dispatch(
                'toast',
                type: 'warning',
                title: 'Auswertung bis zum Recovery-Abschluss gesperrt',
            );

            return;
        }

        EvaluateMaddraxikonContributions::dispatch();

        $this->dispatch(
            'toast',
            type: 'success',
            title: 'Maddraxikon-Auswertung eingereiht',
        );
    }

    public function retryContribution(int $contributionId): void
    {
        $this->adminUser();

        if (! config('maddraxikon.features.awards_enabled', false)) {
            $this->dispatch(
                'toast',
                type: 'warning',
                title: 'Baxx-Auswertung ist deaktiviert',
            );

            return;
        }

        $wikiKey = (string) config(
            'maddraxikon.wiki_key',
            'maddraxikon-de',
        );
        $contribution = MaddraxikonContribution::query()
            ->where('wiki_key', $wikiKey)
            ->due()
            ->whereNotNull('last_evaluation_error_at')
            ->findOrFail($contributionId);

        EvaluateMaddraxikonContributions::dispatch(
            contributionId: $contribution->id,
        );

        $this->dispatch(
            'toast',
            type: 'success',
            title: 'Gezielte Prüfung eingereiht',
        );
    }

    public function openLinkCorrection(int $accountLinkId): void
    {
        $this->adminUser();

        $link = $this->correctableLinks()
            ->findOrFail($accountLinkId);

        $this->correctingAccountLinkId = $link->id;
        $this->linkCorrectionReason = '';
        $this->showLinkCorrectionModal = true;
        $this->resetValidation();
    }

    public function cancelLinkCorrection(): void
    {
        $this->adminUser();

        $this->resetLinkCorrectionForm();
    }

    public function correctAccountLink(
        AccountLinkService $accountLinkService
    ): void {
        $admin = $this->adminUser();

        $this->linkCorrectionReason = trim($this->linkCorrectionReason);

        $validated = $this->validate(
            [
                'correctingAccountLinkId' => ['required', 'integer'],
                'linkCorrectionReason' => ['required', 'string', 'max:500'],
            ],
            [
                'linkCorrectionReason.required' => 'Bitte gib eine Begründung für die Zuordnungskorrektur an.',
                'linkCorrectionReason.max' => 'Die Begründung darf höchstens 500 Zeichen enthalten.',
            ],
        );

        $link = MaddraxikonAccountLink::query()
            ->where(
                'wiki_key',
                (string) config('maddraxikon.wiki_key', 'maddraxikon-de'),
            )
            ->findOrFail((int) $validated['correctingAccountLinkId']);

        try {
            $accountLinkService->releaseDisconnectedLink(
                $admin,
                $link,
                $validated['linkCorrectionReason'],
            );
        } catch (InvalidArgumentException|LogicException $exception) {
            $this->addError(
                'linkCorrectionReason',
                $exception->getMessage(),
            );

            return;
        }

        $this->resetLinkCorrectionForm();
        $this->dispatch(
            'toast',
            type: 'success',
            title: 'Maddraxikon-Zuordnung zur Neuverknüpfung freigegeben',
        );
    }

    public function checkNamespaces(
        MaddraxikonNamespaceHealthService $healthService
    ): void {
        $this->adminUser();

        $this->namespaceHealth = null;
        $this->namespaceHealthError = null;

        try {
            $this->namespaceHealth = $healthService->check();

            $this->dispatch(
                'toast',
                type: $this->namespaceHealth['healthy'] ? 'success' : 'warning',
                title: $this->namespaceHealth['healthy']
                    ? 'Namensräume stimmen überein'
                    : 'Namensraum-Abweichungen gefunden',
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->namespaceHealthError =
                'Die Namensräume konnten nicht geprüft werden. Details stehen im Anwendungsprotokoll.';

            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Namensraum-Prüfung fehlgeschlagen',
            );
        }
    }

    public function openReversal(int $rewardEventId): void
    {
        $this->adminUser();

        $rewardEvent = $this->reversibleRewardEvents()
            ->findOrFail($rewardEventId);

        $this->reversingRewardEventId = $rewardEvent->id;
        $this->reversalReason = '';
        $this->showReversalModal = true;
        $this->resetValidation();
    }

    public function cancelReversal(): void
    {
        $this->adminUser();

        $this->resetReversalForm();
    }

    public function reverseRewardEvent(
        MaddraxikonRewardService $rewardService
    ): void {
        $admin = $this->adminUser();

        $this->reversalReason = trim($this->reversalReason);

        $validated = $this->validate(
            [
                'reversingRewardEventId' => ['required', 'integer'],
                'reversalReason' => ['required', 'string', 'max:1000'],
            ],
            [
                'reversalReason.required' => 'Bitte gib eine Begründung für die Gegenbuchung an.',
                'reversalReason.max' => 'Die Begründung darf höchstens 1000 Zeichen enthalten.',
            ],
        );

        $rewardEvent = $this->reversibleRewardEvents()
            ->findOrFail((int) $validated['reversingRewardEventId']);
        try {
            $rewardService->reverse(
                $rewardEvent,
                $admin,
                $validated['reversalReason'],
            );
        } catch (InvalidArgumentException|LogicException $exception) {
            $this->addError('reversalReason', $exception->getMessage());

            return;
        }

        $this->resetReversalForm();
        $this->dispatch(
            'toast',
            type: 'success',
            title: 'Baxx-Gutschrift gegengebucht',
        );
    }

    public function render(): View
    {
        $wikiKey = (string) config(
            'maddraxikon.wiki_key',
            'maddraxikon-de',
        );
        $rawContributionCounts = MaddraxikonContribution::query()
            ->where('wiki_key', $wikiKey)
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $contributionCounts = [];

        foreach (MaddraxikonContributionStatus::cases() as $status) {
            $contributionCounts[$status->value] = (int) (
                $rawContributionCounts[$status->value] ?? 0
            );
        }

        $linkQuery = MaddraxikonAccountLink::query()
            ->where('wiki_key', $wikiKey)
            ->with('user:id,name');
        $linkStatus = MaddraxikonAccountLinkStatus::tryFrom(
            $this->linkStatusFilter,
        );

        if ($linkStatus !== null) {
            $linkQuery->where('status', $linkStatus);
        }

        $contributionQuery = MaddraxikonContribution::query()
            ->where('wiki_key', $wikiKey)
            ->with([
                'user:id,name',
                'accountLink:id,wiki_username',
            ]);

        if ($this->contributionStatusFilter === 'technical') {
            $contributionQuery
                ->where('status', MaddraxikonContributionStatus::Pending)
                ->whereNotNull('last_evaluation_error_at');
        } elseif (
            ($contributionStatus = MaddraxikonContributionStatus::tryFrom(
                $this->contributionStatusFilter,
            )) !== null
        ) {
            $contributionQuery->where('status', $contributionStatus);
        }

        $rewardQuery = MaddraxikonRewardEvent::query()
            ->where('wiki_key', $wikiKey)
            ->with([
                'user:id,name',
                'accountLink:id,wiki_username',
                'reversedBy:id,name',
            ]);
        $rewardStatus = MaddraxikonRewardEventStatus::tryFrom(
            $this->rewardStatusFilter,
        );

        if ($rewardStatus !== null) {
            $rewardQuery->where('status', $rewardStatus);
        }

        return view('livewire.maddraxikon-admin', [
            'wikiKey' => $wikiKey,
            'wikiBaseUrl' => (string) config('maddraxikon.base_url'),
            'timezone' => (string) config(
                'maddraxikon.timezone',
                'Europe/Berlin',
            ),
            'featureSwitches' => [
                'linking' => [
                    'label' => 'OAuth-Verknüpfung',
                    'enabled' => (bool) config(
                        'maddraxikon.features.linking_enabled',
                        false,
                    ),
                ],
                'sync' => [
                    'label' => 'Beitrags-Synchronisation',
                    'enabled' => (bool) config(
                        'maddraxikon.features.sync_enabled',
                        false,
                    ),
                ],
                'awards' => [
                    'label' => 'Baxx-Auswertung',
                    'enabled' => (bool) config(
                        'maddraxikon.features.awards_enabled',
                        false,
                    ),
                ],
            ],
            'syncState' => MaddraxikonSyncState::query()
                ->where('wiki_key', $wikiKey)
                ->first(),
            'contributionCounts' => $contributionCounts,
            'contributionStatusLabels' => [
                MaddraxikonContributionStatus::Pending->value => 'Ausstehend',
                MaddraxikonContributionStatus::Qualified->value => 'Qualifiziert',
                MaddraxikonContributionStatus::Rejected->value => 'Abgelehnt',
                MaddraxikonContributionStatus::Awarded->value => 'Gutgeschrieben',
            ],
            'technicalFailureCount' => MaddraxikonContribution::query()
                ->where('wiki_key', $wikiKey)
                ->where('status', MaddraxikonContributionStatus::Pending)
                ->whereNotNull('last_evaluation_error_at')
                ->count(),
            'activeLinkCount' => MaddraxikonAccountLink::query()
                ->where('wiki_key', $wikiKey)
                ->where('status', MaddraxikonAccountLinkStatus::Active)
                ->count(),
            'recentLinks' => $linkQuery
                ->latest('updated_at')
                ->limit(50)
                ->get(),
            'recentLinkCorrections' => MaddraxikonAccountLinkCorrection::query()
                ->where('wiki_key', $wikiKey)
                ->with([
                    'actor:id,name',
                    'affectedUser:id,name',
                ])
                ->latest('corrected_at')
                ->limit(20)
                ->get(),
            'linkStatusLabels' => [
                MaddraxikonAccountLinkStatus::Active->value => 'Aktiv',
                MaddraxikonAccountLinkStatus::Disconnected->value => 'Getrennt',
            ],
            'recentContributions' => $contributionQuery
                ->latest('occurred_at_epoch')
                ->latest('occurred_at')
                ->latest('revision_id')
                ->limit(50)
                ->get(),
            'recentRewardEvents' => $rewardQuery
                ->latest('created_at')
                ->limit(50)
                ->get(),
            'rewardStatusLabels' => [
                MaddraxikonRewardEventStatus::EvaluatedNoAward->value => 'Geprüft, ohne Gutschrift',
                MaddraxikonRewardEventStatus::Awarded->value => 'Gutgeschrieben',
                MaddraxikonRewardEventStatus::Rejected->value => 'Abgelehnt',
                MaddraxikonRewardEventStatus::Reversed->value => 'Gegengebucht',
            ],
            'rewardActionLabels' => [
                MaddraxikonRewardEvent::ACTION_EDIT_SESSION => 'Bearbeitungssitzung',
                MaddraxikonRewardEvent::ACTION_NEW_ARTICLE => 'Neuer Artikel',
            ],
        ])->layout('layouts.app', [
            'title' => 'Maddraxikon-Baxx - Admin',
        ]);
    }

    private function correctableLinks()
    {
        return MaddraxikonAccountLink::query()
            ->where(
                'wiki_key',
                (string) config('maddraxikon.wiki_key', 'maddraxikon-de'),
            )
            ->where('status', MaddraxikonAccountLinkStatus::Disconnected)
            ->whereNotNull('disconnected_at');
    }

    private function reversibleRewardEvents()
    {
        return MaddraxikonRewardEvent::query()
            ->where(
                'status',
                MaddraxikonRewardEventStatus::Awarded->value,
            )
            ->where('awarded_points', '>', 0)
            ->whereNull('reversal_user_point_id');
    }

    private function adminUser(): User
    {
        $user = Auth::user();

        abort_unless(
            $user instanceof User
                && $user->hasAnyMitgliederTeamRole(Role::Admin),
            403,
        );

        return $user;
    }

    private function resetLinkCorrectionForm(): void
    {
        $this->showLinkCorrectionModal = false;
        $this->correctingAccountLinkId = null;
        $this->linkCorrectionReason = '';
        $this->resetValidation();
    }

    private function resetReversalForm(): void
    {
        $this->showReversalModal = false;
        $this->reversingRewardEventId = null;
        $this->reversalReason = '';
        $this->resetValidation();
    }
}
