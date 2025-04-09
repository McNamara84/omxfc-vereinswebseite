<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\User;
use Illuminate\Support\Facades\Http;

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
           
        }


        return view('maddraxiversum.index', [
            'showMap' => $showMap,
            'userPoints' => $userPoints,
            'requiredPoints' => $requiredPoints,
            'tileUrl' => 'https://mapdraxv2.maddraxikon.com/v2/{z}/{x}/{y}.png' // URL-Muster für die Tiles
        ]);
    }

    public function getCities()
    {
        $apiUrl = 'https://de.maddraxikon.com/api.php?action=ask&query=[[Kategorie:St%C3%A4dte%20in%20Amraka]]||[[Kategorie:St%C3%A4dte%20in%20Ausala]]||[[Kategorie:St%C3%A4dte%20in%20Euree]]||[[Kategorie:St%C3%A4dte%20in%20Meeraka]]||[[Kategorie:St%C3%A4dte%20in%20Aiaa]]||[[Kategorie:St%C3%A4dte%20in%20Afra]]||[[Kategorie:St%C3%A4dte%20in%20der%20Antakis]]|?Koordinaten|limit%3D400&format=json';

        $response = Http::get($apiUrl);

        return $response->json();
    }
}
