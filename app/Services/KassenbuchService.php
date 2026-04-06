<?php

namespace App\Services;

use App\Models\Kassenstand;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;

class KassenbuchService
{
    public function getOrCreateKassenstand(Team $team): Kassenstand
    {
        try {
            return Kassenstand::firstOrCreate(
                ['team_id' => $team->id],
                ['betrag' => 0.00, 'letzte_aktualisierung' => now()]
            );
        } catch (UniqueConstraintViolationException) {
            return Kassenstand::where('team_id', $team->id)->firstOrFail();
        }
    }

    public function checkRenewalWarning(User $user): bool
    {
        if (! $user->bezahlt_bis) {
            return false;
        }

        $today = Carbon::now();
        $expiryDate = $user->bezahlt_bis instanceof Carbon
            ? $user->bezahlt_bis
            : Carbon::parse((string) $user->bezahlt_bis);
        $daysUntilExpiry = $today->diffInDays($expiryDate, false);

        return $daysUntilExpiry > 0 && $daysUntilExpiry <= 30;
    }
}
