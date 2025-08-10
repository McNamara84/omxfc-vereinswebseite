<?php

namespace App\Policies;

use App\Models\BookOffer;
use App\Models\User;

class BookOfferPolicy
{
    public function delete(User $user, BookOffer $offer): bool
    {
        return $user->id === $offer->user_id;
    }
}
