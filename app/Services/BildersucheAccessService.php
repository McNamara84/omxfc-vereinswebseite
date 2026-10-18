<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class BildersucheAccessService
{
    public function isEligible(?User $user): bool
    {
        return $user !== null
            && $user->email_verified_at !== null
            && $user->mitgliederTeamRole() instanceof Role
            && $user->mitgliederTeamRole() !== Role::Anwaerter;
    }

    public function hasAccess(User $user): bool
    {
        return (bool) config('bildersuche.enabled') && $this->isEligible($user)
            && ($user->isMemberOfTeam('AG Maddraxikon')
                || app(RewardService::class)->hasUnlockedReward($user, 'bildersuche'));
    }

    public function ensureMemberAccess(): User
    {
        abort_unless(config('bildersuche.enabled'), 404);
        $user = Auth::user();
        abort_unless($this->isEligible($user), 403);

        return $user;
    }

    public function ensureSearchAccess(): User
    {
        $user = $this->ensureMemberAccess();
        abort_unless($this->hasAccess($user), 403);

        return $user;
    }

    public function assertPurchasable(User $user): void
    {
        if (! config('bildersuche.enabled') || ! $this->isEligible($user)) {
            throw ValidationException::withMessages(['reward' => 'Die Bildersuche ist für dein Konto derzeit nicht verfügbar.']);
        }
        if ($user->isMemberOfTeam('AG Maddraxikon')) {
            throw ValidationException::withMessages(['reward' => 'Du hast bereits Zugang durch deine Mitgliedschaft in der AG Maddraxikon.']);
        }
    }
}
