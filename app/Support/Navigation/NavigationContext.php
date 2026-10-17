<?php

namespace App\Support\Navigation;

use App\Enums\PollVisibility;
use App\Enums\Role;
use App\Models\User;
use App\Services\Polls\ActivePollResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class NavigationContext
{
    public function canAccessMembers(?User $user): bool
    {
        if (! $user || ! $user->hasVerifiedEmail() || ! $user->currentTeam) {
            return false;
        }

        $role = $user->mitgliederTeamRole();

        return $role instanceof Role
            && $role !== Role::Anwaerter
            && Gate::forUser($user)->allows('access-dashboard');
    }

    public function publicHome(?User $user): string
    {
        return route('home', $this->canAccessMembers($user) ? ['ansicht' => 'oeffentlich'] : []);
    }

    /** @return array<string, mixed> */
    public function polls(): array
    {
        $empty = ['activePollMenuLabel' => null, 'showActivePollForAuth' => false, 'showActivePollForGuest' => false];

        if (! Schema::hasTable('polls')) {
            return $empty;
        }

        return Cache::remember('polls.active_for_menu.v2', now()->addMinutes(10), function () {
            $poll = app(ActivePollResolver::class)->current();
            $visible = (bool) $poll?->isWithinVotingWindow();

            return [
                'activePollMenuLabel' => $poll?->menu_label,
                'showActivePollForAuth' => $visible,
                'showActivePollForGuest' => $visible && $poll->visibility === PollVisibility::Public,
            ];
        });
    }
}
