<?php

namespace App\Livewire;

use App\Enums\KassenbuchEditReasonType;
use App\Enums\Role;
use App\Models\KassenbuchEditRequest;
use App\Models\KassenbuchEntry;
use App\Models\Kassenstand;
use App\Services\MembersTeamProvider;
use App\Services\UserRoleService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class KassenbuchIndex extends Component
{
    private UserRoleService $userRoleService;

    private MembersTeamProvider $membersTeamProvider;

    public function boot(
        UserRoleService $userRoleService,
        MembersTeamProvider $membersTeamProvider,
    ): void {
        $this->userRoleService = $userRoleService;
        $this->membersTeamProvider = $membersTeamProvider;
    }

    #[Computed]
    public function userRole(): Role
    {
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        return $this->userRoleService->getRole(Auth::user(), $team);
    }

    #[Computed]
    public function canViewKassenbuch(): bool
    {
        return Auth::user()->can('viewAll', KassenbuchEntry::class);
    }

    #[Computed]
    public function canManageKassenbuch(): bool
    {
        return Auth::user()->can('manage', KassenbuchEntry::class);
    }

    #[Computed]
    public function canProcessEditRequests(): bool
    {
        return Auth::user()->can('processEditRequest', KassenbuchEntry::class);
    }

    #[Computed]
    public function kassenstand(): Kassenstand
    {
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        return $this->getOrCreateKassenstand($team);
    }

    #[Computed]
    public function members(): ?Collection
    {
        if (! $this->canViewKassenbuch) {
            return null;
        }

        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        return $team->activeUsers()
            ->orderBy('bezahlt_bis')
            ->get();
    }

    #[Computed]
    public function kassenbuchEntries(): ?Collection
    {
        if (! $this->canViewKassenbuch) {
            return null;
        }

        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        return KassenbuchEntry::where('team_id', $team->id)
            ->with(['pendingEditRequest', 'approvedEditRequest', 'creator', 'lastEditor'])
            ->orderBy('buchungsdatum', 'desc')
            ->get();
    }

    #[Computed]
    public function pendingEditRequests(): ?Collection
    {
        if (! $this->canProcessEditRequests) {
            return null;
        }

        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        return KassenbuchEditRequest::with(['entry', 'requester'])
            ->where('status', KassenbuchEditRequest::STATUS_PENDING)
            ->whereHas('entry', fn ($q) => $q->where('team_id', $team->id))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    #[Computed]
    public function editReasonTypes(): array
    {
        return KassenbuchEditReasonType::cases();
    }

    #[Computed]
    public function memberData()
    {
        return Auth::user();
    }

    #[Computed]
    public function renewalWarning(): bool
    {
        $user = Auth::user();

        if (! $user->bezahlt_bis) {
            return false;
        }

        $today = Carbon::now();
        $expiryDate = $user->bezahlt_bis instanceof Carbon
            ? $user->bezahlt_bis
            : Carbon::parse((string) $user->bezahlt_bis);
        $daysUntilExpiry = $today->diffInDays($expiryDate, false);

        return $daysUntilExpiry > 0 && $daysUntilExpiry <= 30;
    }

    private function getOrCreateKassenstand($team): Kassenstand
    {
        try {
            return Kassenstand::firstOrCreate(
                ['team_id' => $team->id],
                ['betrag' => 0.00, 'letzte_aktualisierung' => now()]
            );
        } catch (UniqueConstraintViolationException) {
            return Kassenstand::where('team_id', $team->id)->firstOrFail();
        }
    }

    public function render()
    {
        return view('livewire.kassenbuch-index')
            ->layout('layouts.app', ['title' => 'Kassenbuch']);
    }
}
