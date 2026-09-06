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
use App\Models\MaddraxikonRewardPolicy;
use App\Models\MaddraxikonSyncState;
use App\Models\User;
use App\Services\Maddraxikon\AccountLinkService;
use App\Services\Maddraxikon\MaddraxikonNamespaceHealthService;
use App\Services\Maddraxikon\MaddraxikonRewardPolicyPublisher;
use App\Services\Maddraxikon\MaddraxikonRewardPolicyResolver;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    public bool $showPolicyModal = false;

    public ?int $editingPolicyId = null;

    public string $policyName = '';

    public string $policyEffectiveFrom = '';

    public bool $policyEditSessionsEnabled = true;

    /** @var list<array{minimum_added_bytes: int|string, points: int|string}> */
    public array $policyTiers = [];

    public bool $policyNewArticlesEnabled = true;

    public int $policyNewArticleMinimumBytes = 500;

    public int $policyNewArticlePoints = 5;

    public int $policyPreviewBytes = 500;

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

    public function openCreatePolicy(): void
    {
        $this->adminUser();
        $this->resetPolicyForm();
        $this->policyName = 'Maddraxikon-Baxx-Regel';
        $this->policyEffectiveFrom = CarbonImmutable::now(
            (string) config('maddraxikon.timezone', 'Europe/Berlin')
        )->addDay()->startOfHour()->format('Y-m-d\TH:i');
        $this->policyTiers = [
            ['minimum_added_bytes' => 100, 'points' => 1],
        ];
        $this->showPolicyModal = true;
    }

    public function copyPolicy(int $policyId): void
    {
        $this->adminUser();
        $policy = MaddraxikonRewardPolicy::query()
            ->with('tiers')
            ->findOrFail($policyId);

        $this->resetPolicyForm();
        $this->policyName = 'Kopie von '.$policy->name;
        $this->policyEffectiveFrom = CarbonImmutable::now(
            (string) config('maddraxikon.timezone', 'Europe/Berlin')
        )->addDay()->startOfHour()->format('Y-m-d\TH:i');
        $this->policyEditSessionsEnabled = $policy->edit_sessions_enabled;
        $this->policyTiers = $policy->tiers
            ->map(fn ($tier): array => [
                'minimum_added_bytes' => $tier->minimum_added_bytes,
                'points' => $tier->points,
            ])
            ->values()
            ->all();
        $this->policyNewArticlesEnabled = $policy->new_articles_enabled;
        $this->policyNewArticleMinimumBytes = (int) ($policy->new_article_minimum_bytes ?? 500);
        $this->policyNewArticlePoints = (int) ($policy->new_article_points ?? 5);
        $this->showPolicyModal = true;
    }

    public function openEditPolicy(int $policyId): void
    {
        $this->adminUser();
        $policy = MaddraxikonRewardPolicy::query()
            ->where('status', MaddraxikonRewardPolicy::STATUS_DRAFT)
            ->with('tiers')
            ->findOrFail($policyId);

        $this->editingPolicyId = $policy->id;
        $this->policyName = $policy->name;
        $this->policyEffectiveFrom = $policy->effective_from
            ? $policy->effective_from
                ->setTimezone((string) config('maddraxikon.timezone', 'Europe/Berlin'))
                ->format('Y-m-d\TH:i')
            : '';
        $this->policyEditSessionsEnabled = $policy->edit_sessions_enabled;
        $this->policyTiers = $policy->tiers
            ->map(fn ($tier): array => [
                'minimum_added_bytes' => $tier->minimum_added_bytes,
                'points' => $tier->points,
            ])
            ->values()
            ->all();
        $this->policyNewArticlesEnabled = $policy->new_articles_enabled;
        $this->policyNewArticleMinimumBytes = (int) ($policy->new_article_minimum_bytes ?? 500);
        $this->policyNewArticlePoints = (int) ($policy->new_article_points ?? 5);
        $this->policyPreviewBytes = 500;
        $this->showPolicyModal = true;
        $this->resetValidation();
    }

    public function addPolicyTier(): void
    {
        $this->adminUser();
        $this->policyTiers[] = [
            'minimum_added_bytes' => 0,
            'points' => 1,
        ];
    }

    public function removePolicyTier(int $index): void
    {
        $this->adminUser();
        unset($this->policyTiers[$index]);
        $this->policyTiers = array_values($this->policyTiers);
    }

    public function savePolicyDraft(): void
    {
        $this->persistPolicyDraft();
        $this->resetPolicyForm();
        $this->dispatch(
            'toast',
            type: 'success',
            title: 'Maddraxikon-Regelentwurf gespeichert',
        );
    }

    public function publishPolicy(
        MaddraxikonRewardPolicyPublisher $publisher,
    ): void {
        $admin = $this->adminUser();
        $draft = $this->validatedPolicyDraftData();
        $publisher->publishDraft(
            $this->editingPolicyId,
            $draft['attributes'],
            $draft['tiers'],
            $admin,
        );
        $this->resetPolicyForm();
        $this->dispatch(
            'toast',
            type: 'success',
            title: 'Maddraxikon-Regelversion veröffentlicht',
        );
    }

    public function deletePolicyDraft(int $policyId): void
    {
        $this->adminUser();
        $policy = MaddraxikonRewardPolicy::query()
            ->where('status', MaddraxikonRewardPolicy::STATUS_DRAFT)
            ->findOrFail($policyId);
        $policy->delete();
        $this->dispatch(
            'toast',
            type: 'success',
            title: 'Maddraxikon-Regelentwurf gelöscht',
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
                'rewardPolicy:id,name',
            ]);
        $rewardStatus = MaddraxikonRewardEventStatus::tryFrom(
            $this->rewardStatusFilter,
        );

        if ($rewardStatus !== null) {
            $rewardQuery->where('status', $rewardStatus);
        }

        $policyResolver = app(MaddraxikonRewardPolicyResolver::class);
        $currentPolicy = $policyResolver->current();
        $nextPolicy = $policyResolver->next();
        $policyPreviewPoints = collect($this->policyTiers)
            ->filter(fn (array $tier): bool => (
                (int) ($tier['minimum_added_bytes'] ?? 0) <= $this->policyPreviewBytes
            ))
            ->sortByDesc(fn (array $tier): int => (int) ($tier['minimum_added_bytes'] ?? 0))
            ->first()['points'] ?? 0;

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
            'currentPolicy' => $currentPolicy,
            'nextPolicy' => $nextPolicy,
            'rewardPolicies' => MaddraxikonRewardPolicy::query()
                ->with(['tiers', 'creator:id,name', 'publisher:id,name'])
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [
                    MaddraxikonRewardPolicy::STATUS_DRAFT,
                ])
                ->orderByDesc('effective_from_epoch')
                ->latest('id')
                ->get(),
            'policyPreviewPoints' => max(0, (int) $policyPreviewPoints),
        ])->layout('layouts.member', [
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

    private function persistPolicyDraft(): MaddraxikonRewardPolicy
    {
        $admin = $this->adminUser();
        $draft = $this->validatedPolicyDraftData();

        return DB::transaction(function () use (
            $admin,
            $draft,
        ): MaddraxikonRewardPolicy {
            $policy = $this->editingPolicyId
                ? MaddraxikonRewardPolicy::query()
                    ->where('status', MaddraxikonRewardPolicy::STATUS_DRAFT)
                    ->lockForUpdate()
                    ->findOrFail($this->editingPolicyId)
                : new MaddraxikonRewardPolicy([
                    'created_by' => $admin->id,
                ]);

            $policy->fill([
                ...$draft['attributes'],
                'status' => MaddraxikonRewardPolicy::STATUS_DRAFT,
            ]);
            $policy->save();
            $policy->tiers()->delete();

            foreach ($draft['tiers'] as $tier) {
                $policy->tiers()->create($tier);
            }

            $this->editingPolicyId = $policy->id;

            return $policy->fresh('tiers');
        }, 3);
    }

    /**
     * @return array{
     *     attributes: array{
     *         name: string,
     *         effective_from: CarbonImmutable,
     *         edit_sessions_enabled: bool,
     *         new_articles_enabled: bool,
     *         new_article_minimum_bytes: int,
     *         new_article_points: int
     *     },
     *     tiers: list<array{minimum_added_bytes: int, points: int}>
     * }
     */
    private function validatedPolicyDraftData(): array
    {
        $validated = $this->validate([
            'policyName' => ['required', 'string', 'max:255'],
            'policyEffectiveFrom' => ['required', 'date_format:Y-m-d\\TH:i'],
            'policyEditSessionsEnabled' => ['boolean'],
            'policyTiers' => ['array'],
            'policyTiers.*.minimum_added_bytes' => [
                'required',
                'integer',
                'min:0',
                'max:4294967295',
                'distinct',
            ],
            'policyTiers.*.points' => ['required', 'integer', 'min:0', 'max:1000000'],
            'policyNewArticlesEnabled' => ['boolean'],
            'policyNewArticleMinimumBytes' => ['required', 'integer', 'min:0'],
            'policyNewArticlePoints' => ['required', 'integer', 'min:0', 'max:1000000'],
        ], [
            'policyTiers.*.minimum_added_bytes.distinct' => 'Jede Byte-Grenze darf nur einmal vorkommen.',
        ]);

        if ($this->policyEditSessionsEnabled && $this->policyTiers === []) {
            throw ValidationException::withMessages([
                'policyTiers' => 'Für aktive Bearbeitungssitzungen ist mindestens eine Stufe erforderlich.',
            ]);
        }

        $effectiveFrom = CarbonImmutable::createFromFormat(
            'Y-m-d\TH:i',
            $validated['policyEffectiveFrom'],
            (string) config('maddraxikon.timezone', 'Europe/Berlin'),
        )->utc();
        $tiers = collect($validated['policyTiers'])
            ->map(fn (array $tier): array => [
                'minimum_added_bytes' => (int) $tier['minimum_added_bytes'],
                'points' => (int) $tier['points'],
            ])
            ->sortBy('minimum_added_bytes')
            ->values()
            ->all();

        $duplicateEffectiveFrom = MaddraxikonRewardPolicy::query()
            ->where('effective_from_epoch', $effectiveFrom->getTimestamp())
            ->when(
                $this->editingPolicyId !== null,
                fn ($query) => $query->whereKeyNot($this->editingPolicyId)
            )
            ->exists();

        if ($duplicateEffectiveFrom) {
            throw ValidationException::withMessages([
                'policyEffectiveFrom' => 'Zu diesem Zeitpunkt existiert bereits eine Regelversion.',
            ]);
        }

        return [
            'attributes' => [
                'name' => trim((string) $validated['policyName']),
                'effective_from' => $effectiveFrom,
                'edit_sessions_enabled' => (bool) $validated['policyEditSessionsEnabled'],
                'new_articles_enabled' => (bool) $validated['policyNewArticlesEnabled'],
                'new_article_minimum_bytes' => (int) $validated['policyNewArticleMinimumBytes'],
                'new_article_points' => (int) $validated['policyNewArticlePoints'],
            ],
            'tiers' => $tiers,
        ];
    }

    private function resetPolicyForm(): void
    {
        $this->showPolicyModal = false;
        $this->editingPolicyId = null;
        $this->policyName = '';
        $this->policyEffectiveFrom = '';
        $this->policyEditSessionsEnabled = true;
        $this->policyTiers = [];
        $this->policyNewArticlesEnabled = true;
        $this->policyNewArticleMinimumBytes = 500;
        $this->policyNewArticlePoints = 5;
        $this->policyPreviewBytes = 500;
        $this->resetValidation();
    }
}
