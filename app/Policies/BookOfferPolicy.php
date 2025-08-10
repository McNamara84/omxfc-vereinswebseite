<?php

namespace App\Policies;

use App\Models\BookOffer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookOfferPolicy
{
    use HandlesAuthorization;

    public function delete(User $user, BookOffer $offer): bool
    {
        return $user->id === $offer->user_id;
    }
}
