<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\UserPoint;
use Illuminate\Support\Facades\Auth;
use App\Services\MemberMapCacheService;

class MitgliederKarteController extends Controller
{
    public function __construct(protected MemberMapCacheService $memberMapCacheService)
    {
    }

    public function index()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        // Prüfen, ob der Nutzer mindestens einen Punkt oder eine erledigte Challenge hat
        $memberTeam = Team::where('name', 'Mitglieder')->first();

        if ($memberTeam) {
            $userPoints = UserPoint::where('user_id', $user->id)
                ->where('team_id', $memberTeam->id)
                ->count();

            $hasAccess = $userPoints > 0;
        } else {
            $hasAccess = false;
        }

        // Wenn der Nutzer keine Punkte hat, zeigen wir eine Meldung an
        if (! $hasAccess) {
            return view('mitglieder.karte-locked');
        }

        $mapData = $this->memberMapCacheService->getMemberMapData($team);
        $memberData = $mapData['memberData'];
        $centerLat = $mapData['centerLat'];
        $centerLon = $mapData['centerLon'];

        // Regionalstammtische definieren
        $stammtischData = [
            [
                'name' => 'Regionalstammtisch München',
                'lat' => 48.12896638040895,
                'lon' => 11.609687426607499,
                'address' => 'München, Bayern',
                'info' => 'Jeden ersten Donnerstag im Monat',
            ],
            [
                'name' => 'Regionalstammtisch Berlin',
                'lat' => 52.4612530430613,
                'lon' => 13.318158251047139,
                'address' => 'Berlin',
                'info' => 'Jeden siebten Tag in geraden Monaten',
            ],
            [
                'name' => 'Regionalstammtisch Brandenburg',
                'lat' => 52.40084391069621,
                'lon' => 13.0538574534862,
                'address' => 'Brandenburg',
                'info' => 'Jeden siebten Tag in ungeraden Monaten',
            ],
        ];

        return view('mitglieder.karte', [
            'memberData' => json_encode($memberData),
            'stammtischData' => json_encode($stammtischData),
            'centerLat' => 51.1657, // Mitte von Deutschland
            'centerLon' => 10.4515,
            'membersCenterLat' => $centerLat,
            'membersCenterLon' => $centerLon,
        ]);
    }

}