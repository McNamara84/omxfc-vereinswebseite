<?php

namespace App\Policies;

use App\Models\BookRequest;
use App\Models\User;

class BookRequestPolicy
{
    public function update(User $user, BookRequest $bookRequest): bool
    {
        return $user->id === $bookRequest->user_id;
    }

    public function delete(User $user, BookRequest $bookRequest): bool
    {
        return $user->id === $bookRequest->user_id;
    }
}
