<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Models\User;
use App\Services\MembersTeamProvider;
use App\Services\UserRoleService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class MitgliederIndex extends Component
{
    #[Url(as: 'sort', except: 'nachname')]
    public string $sortBy = 'nachname';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    #[Url(except: false)]
    public bool $nurOnline = false;

    private UserRoleService $userRoleService;

    private MembersTeamProvider $membersTeamProvider;

    private const ALLOWED_SORT_FIELDS = ['nachname', 'role', 'mitgliedsbeitrag', 'mitglied_seit', 'last_activity'];

    private const ROLE_RANKS = [
        'Mitglied' => 1,
        'Ehrenmitglied' => 2,
        'Kassenwart' => 3,
        'Vorstand' => 4,
        'Admin' => 5,
    ];

    public function boot(
        UserRoleService $userRoleService,
        MembersTeamProvider $membersTeamProvider,
    ): void {
        $this->userRoleService = $userRoleService;
        $this->membersTeamProvider = $membersTeamProvider;
    }

    public function mount(): void
    {
        // Validate sort params from URL
        if (! in_array($this->sortBy, self::ALLOWED_SORT_FIELDS)) {
            $this->sortBy = 'nachname';
        }
        if (! in_array($this->sortDir, ['asc', 'desc'])) {
            $this->sortDir = 'asc';
        }
    }

    #[Computed]
    public function members(): Collection
    {
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();
        $query = $team->activeUsers();

        if ($this->nurOnline) {
            $query->whereIn('users.id', $this->onlineUserIds);
        }

        if ($this->sortBy === 'role') {
            return $query->orderByPivot('role', $this->sortDir)->get();
        }

        return $query->orderBy($this->sortBy, $this->sortDir)->get();
    }

    #[Computed]
    public function onlineUserIds(): array
    {
        return DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->pluck('user_id')
            ->toArray();
    }

    #[Computed]
    public function canViewDetails(): bool
    {
        return Auth::user()->can('manage', User::class);
    }

    #[Computed]
    public function currentUserRank(): int
    {
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();
        $role = $this->userRoleService->getRole(Auth::user(), $team);

        return self::ROLE_RANKS[$role->value] ?? 0;
    }

    public function getRoleRanks(): array
    {
        return self::ROLE_RANKS;
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::ALLOWED_SORT_FIELDS)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = $column === 'last_activity' ? 'desc' : 'asc';
        }

        unset($this->members);
    }

    public function updatedNurOnline(): void
    {
        unset($this->members);
    }

    public function render()
    {
        return view('livewire.mitglieder-index', [
            'roleRanks' => self::ROLE_RANKS,
            'currentUser' => Auth::user(),
        ])->layout('layouts.app', ['title' => 'Mitgliederliste']);
    }
}
