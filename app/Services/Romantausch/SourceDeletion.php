<?php

namespace App\Services\Romantausch;

use App\Models\Activity;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;

final class SourceDeletion
{
    /** Called only inside MutationLock with the source's owner already locked. */
    public function delete(BookOffer|BookRequest $source): void
    {
        $swaps = BookSwap::where($source instanceof BookOffer ? 'offer_id' : 'request_id', $source->id)
            ->orderBy('id')->lockForUpdate()->get();
        foreach ($swaps as $swap) {
            if (! $swap->completed_at) {
                $affected = $source instanceof BookOffer ? $swap->request : $swap->offer;
                if ($affected) {
                    Activity::create(['user_id' => $affected->user_id, 'subject_type' => $affected::class, 'subject_id' => $affected->id,
                        'action' => $source instanceof BookOffer ? 'match_cancelled_by_offer_owner' : 'match_cancelled_by_request_owner']);
                }
            }
            $swap->delete();
        }
        $source->delete();
    }
}
