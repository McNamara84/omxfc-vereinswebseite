<?php

namespace App\Services\CoverRatings;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CoverRatingAccessService
{
    public function ensureMemberAccess(): User
    {
        abort_unless((bool) config('cover-ratings.enabled'), 404);

        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $role = $user->mitgliederTeamRole();
        abort_unless($role instanceof Role && $role !== Role::Anwaerter, 403);

        return $user;
    }
}
