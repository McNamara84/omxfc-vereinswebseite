<?php

namespace App\Services;

use App\Models\RpgCharacter;

final class RpgInventoryHistory
{
    public function forCharacter(RpgCharacter $character): array
    {
        return $character->inventoryEntries()->with('transaction.actor')->orderBy('id')->get()->map(fn ($entry) => [
            'id' => $entry->transaction_id, 'date' => $entry->transaction->occurred_on->format('d.m.Y'),
            'booked_at' => $entry->transaction->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i'),
            'title' => $entry->transaction->label(), 'reason' => $entry->transaction->reason,
            'actor' => $entry->transaction->actor?->nicknameOrName() ?? 'Ehemaliges Mitglied',
            'changes' => $entry->changes, 'details' => $entry->transaction->details,
        ])->all();
    }
}
