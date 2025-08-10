<?php

namespace App\Policies;

use App\Models\BookRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookRequestPolicy
{
    use HandlesAuthorization;

    public function delete(User $user, BookRequest $request): bool
    {
        return $user->id === $request->user_id;
    }
}
