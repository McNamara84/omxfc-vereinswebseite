<?php

namespace App\Policies;

use App\Models\BookRequest;
use App\Models\User;

class BookRequestPolicy
{
    public function delete(User $user, BookRequest $request): bool
    {
        return $user->id === $request->user_id;
    }
}
