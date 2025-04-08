<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\User;

class MaddraxiversumController extends Controller
{
    /**
     * Zeigt die Maddraxiversum-Seite mit der Karte an,
     * wenn der Benutzer genügend Punkte hat.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $currentTeam = $user->currentTeam; // Holt das aktuelle Team des Benutzers
        $requiredPoints = 10; // Mindestpunktzahl für den Zugriff
        $userPoints = 0;
        $showMap = false;

        // Stelle sicher, dass der Benutzer einem Team zugeordnet ist
        if ($currentTeam) {
            $userPoints = $user->totalPointsForTeam($currentTeam);
            if ($userPoints >= $requiredPoints) {
                $showMap = true;
            }
        } else {
            // Optional: Handle den Fall, dass der Benutzer kein aktuelles Team hat
            // Eventuell Weiterleitung oder Fehlermeldung
             // Für dieses Beispiel gehen wir davon aus, dass jeder eingeloggte Benutzer ein Team hat.
             // Wenn nicht, wird die Karte einfach nicht angezeigt.
        }


        return view('maddraxiversum.index', [
            'showMap' => $showMap,
            'userPoints' => $userPoints,
            'requiredPoints' => $requiredPoints,
            'tileUrl' => 'https://mapdraxv2.maddraxikon.com/v2/{z}/{x}/{y}.png' // URL-Muster für die Tiles
        ]);
    }
}
