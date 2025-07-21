<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\UserPoint;
use App\Models\Team;

class MitgliederKarteController extends Controller
{
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
        if (!$hasAccess) {
            return view('mitglieder.karte-locked');
        }
        
        // Nur Nutzer mit Rollen außer "Anwärter" anzeigen
        $members = $team->users()
            ->wherePivotNotIn('role', ['Anwärter'])
            ->get();
            
        // Geodaten für die Mitglieder sammeln
        $memberData = [];
        $totalLat = 0;
        $totalLon = 0;
        $memberCount = 0;
        
        foreach ($members as $member) {
            // Nur Mitglieder mit PLZ berücksichtigen
            if (!empty($member->plz)) {
                // Koordinaten für die PLZ abrufen (mit Caching)
                $coordinates = $this->getCoordinatesForPostalCode($member->plz, $member->land);
                
                if ($coordinates) {
                    // Kleine zufällige Verschiebung hinzufügen (max 500m)

                    // Mittelpunktberechnung (ohne Jitter für Genauigkeit)
                    $totalLat += $coordinates['lat'];
                    $totalLon += $coordinates['lon'];
                    $memberCount++;

                    $jitter = $this->addJitter($coordinates['lat'], $coordinates['lon']);
                    
                    $memberData[] = [
                        'name' => $member->name,
                        'city' => $member->stadt,
                        'role' => $member->membership->role,
                        'lat' => $jitter['lat'],
                        'lon' => $jitter['lon'],
                        'profile_url' => route('profile.view', $member->id)
                    ];
                }
            }
        }

        $centerLat = $memberCount > 0 ? $totalLat / $memberCount : 51.1657;
        $centerLon = $memberCount > 0 ? $totalLon / $memberCount : 10.4515;
        
        // Regionalstammtische definieren
        $stammtischData = [
            [
                'name' => 'Regionalstammtisch München',
                'lat' => 48.12896638040895,
                'lon' => 11.609687426607499,
                'address' => 'München, Bayern',
                'info' => 'Jeden ersten Donnerstag im Monat'
            ],
            [
                'name' => 'Regionalstammtisch Berlin',
                'lat' => 52.4612530430613,
                'lon' => 13.318158251047139,
                'address' => 'Berlin',
                'info' => 'Jeden siebten Tag in geraden Monaten'
            ],
            [
                'name' => 'Regionalstammtisch Brandenburg',
                'lat' => 52.40084391069621,
                'lon' => 13.0538574534862,
                'address' => 'Brandenburg',
                'info' => 'Jeden siebten Tag in ungeraden Monaten'
            ]
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
    
    /**
     * Fügt eine kleine zufällige Verschiebung hinzu, um Datenschutz zu erhöhen
     */
    private function addJitter($lat, $lon)
    {
        // Zufällige Verschiebung zwischen -0.005 und 0.005 Grad (ca. 300-500m)
        $latJitter = (mt_rand(-50, 50) / 10000);
        $lonJitter = (mt_rand(-50, 50) / 10000);
        
        return [
            'lat' => $lat + $latJitter,
            'lon' => $lon + $lonJitter
        ];
    }
    
    /**
     * Ruft die Koordinaten für eine PLZ ab mit Caching
     */
    private function getCoordinatesForPostalCode($postalCode, $country = 'Deutschland')
    {
        // Cache-Key erstellen
        $cacheKey = "postal_code_" . $country . "_" . $postalCode;
        
        // Prüfen, ob die Daten bereits im Cache sind (30 Tage gültig)
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        // Nominatim API nutzen (OpenStreetMap Geocoding)
        $response = Http::get('https://nominatim.openstreetmap.org/search', [
            'postalcode' => $postalCode,
            'country' => $country,
            'format' => 'json',
            'limit' => 1,
            'email' => config('app.url') // Füge hier deine E-Mail-Adresse ein
        ]);
        
        if ($response->successful() && count($response->json()) > 0) {
            $data = $response->json()[0];
            $result = [
                'lat' => (float) $data['lat'],
                'lon' => (float) $data['lon']
            ];
            
            // Ergebnis im Cache speichern (30 Tage)
            Cache::put($cacheKey, $result, now()->addDays(30));
            
            return $result;
        }
        
        return null;
    }
}