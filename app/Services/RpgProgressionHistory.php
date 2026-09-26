<?php

namespace App\Services;

use App\Models\RpgCharacter;

final class RpgProgressionHistory
{
    public function forCharacter(RpgCharacter $character): array
    {
        $entries = $character->experienceEntries()->with(['award.adventure.user', 'advancement.reviewer'])->orderBy('id')->get();
        $received = 0;
        $spent = 0;
        $rows = [];
        foreach ($entries as $entry) {
            $received += max(0, $entry->amount);
            $spent += max(0, -$entry->amount);
            $award = $entry->award;
            $request = $entry->advancement;
            $rows[] = [
                'date' => $entry->created_at->format('d.m.Y H:i'),
                'title' => $award ? $award->adventure->title : 'Charakterverbesserung',
                'completed_on' => $award?->adventure?->completed_on?->format('d.m.Y'),
                'amount' => $entry->amount, 'balance' => $received - $spent,
                'calculated_points' => $award?->calculated_points,
                'criteria' => $award?->criteria,
                'minutes' => $award?->adventure?->minutes,
                'cycle_bonus' => $award?->adventure?->cycle_bonus,
                'reason' => $award?->reason,
                'changes' => $request?->changes ?? [],
                'actor' => ($award ? $award->adventure->user : $request?->reviewer)?->nicknameOrName() ?? 'Ehemaliges Mitglied',
                'rule_version' => $award ? $award->adventure->rule_version : $request?->rule_version,
            ];
        }

        return ['received' => $received, 'spent' => $spent, 'balance' => $received - $spent, 'entries' => $rows];
    }
}
