<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\Facades\Auth;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = Auth::user();
        
        if ($user->currentTeam && $user->currentTeam->users()->where('user_id', $user->id)->wherePivot('role', 'Anwärter')->exists()) {
            Auth::logout();

            return redirect()->route('login')->withErrors([
                'email' => 'Dein Mitgliedschaftsantrag wird derzeit noch bearbeitet. Bitte warte auf eine E-Mail vom Vorstand. Diese erhältst du nach erfolgreicher Prüfung deines Antrags und nach Zahlungseingang deines Mitgliedsbeitrags für das erste Jahr.'
            ]);
        }

        return redirect()->intended(config('fortify.home'));
    }
}
