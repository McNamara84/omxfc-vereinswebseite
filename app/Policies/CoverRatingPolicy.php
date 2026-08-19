<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\CoverRating;
use App\Models\User;

class CoverRatingPolicy
{
    public function viewAny(User $user): bool
    {
        $role = $user->mitgliederTeamRole();

        return $user->hasVerifiedEmail()
            && $role instanceof Role
            && $role !== Role::Anwaerter;
    }

    public function update(User $user, CoverRating $coverRating): bool
    {
        return $this->viewAny($user) && $user->id === $coverRating->user_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, CoverRating $coverRating): bool
    {
        return $this->update($user, $coverRating);
    }
}
