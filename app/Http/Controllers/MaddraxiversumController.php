<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use App\Models\Mission;
use Carbon\Carbon;

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

        // Immer zeigen, wenn Ehrenmitglied
        if ($user->hasRole('Ehrenmitglied')) {
            $showMap = true;
            $userPoints = $currentTeam ? $user->totalPointsForTeam($currentTeam) : 0;
        } elseif ($currentTeam) {
            // Stelle sicher, dass der Benutzer einem Team zugeordnet ist
            $userPoints = $user->totalPointsForTeam($currentTeam);
            if ($userPoints >= $requiredPoints) {
                $showMap = true;
            }
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

    public function startMission(Request $request)
    {
        $user = Auth::user();

        // Prüfen, ob bereits eine aktive Mission existiert
        if (Mission::where('user_id', $user->id)->where('completed', false)->exists()) {
            return response()->json(['error' => 'Du hast bereits eine laufende Mission.'], 400);
        }

        $missionData = $request->validate([
            'name' => 'required|string',
            'origin' => 'required|string',
            'destination' => 'required|string',
            'travel_duration' => 'required|integer',
            'mission_duration' => 'required|integer',
        ]);

        $startedAt = Carbon::now();
        $arrivalAt = $startedAt->copy()->addSeconds($missionData['travel_duration']);
        $missionEndsAt = $arrivalAt->copy()->addSeconds($missionData['mission_duration'] + $missionData['travel_duration']);

        Mission::create([
            'user_id' => $user->id,
            'name' => $missionData['name'],
            'origin' => $missionData['origin'],
            'destination' => $missionData['destination'],
            'travel_duration' => $missionData['travel_duration'],
            'mission_duration' => $missionData['mission_duration'],
            'started_at' => $startedAt,
            'arrival_at' => $arrivalAt,
            'mission_ends_at' => $missionEndsAt,
        ]);

        return response()->json([
            'message' => 'Mission gestartet!',
            'arrival_at' => $arrivalAt,
            'mission_ends_at' => $missionEndsAt,
        ]);
    }

    public function checkMissionStatus(Request $request)
    {
        try {
            $user = Auth::user();
            \Log::info('Status-Check für Benutzer: ' . $user->id);

            $mission = Mission::where('user_id', $user->id)
                ->where('completed', false)
                ->first();

            if (!$mission) {
                \Log::info('Keine aktive Mission gefunden für Benutzer: ' . $user->id);
                return response()->json(['status' => 'none']);
            }

            \Log::info('Aktive Mission gefunden:', [
                'mission_id' => $mission->id,
                'started_at' => $mission->started_at,
                'arrival_at' => $mission->arrival_at,
                'mission_ends_at' => $mission->mission_ends_at,
                'completed' => $mission->completed,
                'travel_duration' => $mission->travel_duration,
                'mission_duration' => $mission->mission_duration
            ]);

            $now = Carbon::now();
            \Log::info('Aktuelle Zeit: ' . $now);

            // Berechne die Gesamtdauer der Mission
            $totalDuration = $mission->travel_duration + $mission->mission_duration + $mission->travel_duration;
            $expectedEndTime = $mission->started_at->copy()->addSeconds($totalDuration);

            \Log::info('Berechnete Zeiten:', [
                'total_duration' => $totalDuration,
                'expected_end_time' => $expectedEndTime,
                'time_diff' => $now->diffInSeconds($expectedEndTime)
            ]);

            if ($now->greaterThanOrEqualTo($expectedEndTime)) {
                \Log::info('Mission ist beendet, markiere als abgeschlossen');
                // Mission erfolgreich abgeschlossen
                $mission->completed = true;
                $mission->save();

                // Punkte vergeben
                if ($user->currentTeam) {
                    $user->incrementTeamPoints($mission->reward ?? 5);
                    \Log::info('Punkte vergeben:', [
                        'user_id' => $user->id,
                        'team_id' => $user->currentTeam->id,
                        'points' => $mission->reward ?? 5
                    ]);
                } else {
                    \Log::warning('Kein Team gefunden für Benutzer: ' . $user->id);
                }

                return response()->json(['status' => 'completed']);
            } elseif ($now->greaterThanOrEqualTo($mission->arrival_at)) {
                \Log::info('Mission läuft noch');
                return response()->json(['status' => 'in_mission']);
            } else {
                \Log::info('Mission ist noch unterwegs');
                return response()->json(['status' => 'traveling']);
            }
        } catch (\Exception $e) {
            \Log::error('Fehler beim Status-Check:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Ein interner Fehler ist aufgetreten'], 500);
        }
    }

    public function getMissionStatus(Request $request)
    {
        try {
            $user = Auth::user();
            \Log::info('Status-Abfrage für Benutzer: ' . $user->id);

            $mission = Mission::where('user_id', $user->id)
                ->where('completed', false)
                ->first();

            if (!$mission) {
                \Log::info('Keine aktive Mission gefunden für Benutzer: ' . $user->id);
                return response()->json(['status' => 'none']);
            }

            \Log::info('Aktive Mission gefunden:', [
                'mission_id' => $mission->id,
                'started_at' => $mission->started_at,
                'arrival_at' => $mission->arrival_at,
                'mission_ends_at' => $mission->mission_ends_at,
                'completed' => $mission->completed,
                'travel_duration' => $mission->travel_duration,
                'mission_duration' => $mission->mission_duration,
                'origin' => $mission->origin,
                'destination' => $mission->destination
            ]);

            $now = Carbon::now();
            $totalDuration = $mission->travel_duration + $mission->mission_duration + $mission->travel_duration;
            $expectedEndTime = $mission->started_at->copy()->addSeconds($totalDuration);

            // Berechne die aktuelle Position des Gleiters
            $elapsedSeconds = $now->diffInSeconds($mission->started_at);
            $currentPosition = 'traveling';
            $currentLocation = $mission->origin;

            if ($elapsedSeconds < $mission->travel_duration) {
                // Auf dem Hinflug
                $currentLocation = $mission->origin;
            } elseif ($elapsedSeconds < ($mission->travel_duration + $mission->mission_duration)) {
                // Am Zielort
                $currentLocation = $mission->destination;
                $currentPosition = 'in_mission';
            } elseif ($elapsedSeconds < $totalDuration) {
                // Auf dem Rückflug
                $currentLocation = $mission->destination;
            } else {
                // Mission beendet
                $currentLocation = $mission->origin;
                $currentPosition = 'completed';
            }

            return response()->json([
                'status' => $currentPosition,
                'current_location' => $currentLocation,
                'mission' => [
                    'name' => $mission->name,
                    'origin' => $mission->origin,
                    'destination' => $mission->destination,
                    'travel_duration' => $mission->travel_duration,
                    'mission_duration' => $mission->mission_duration,
                    'started_at' => $mission->started_at,
                    'arrival_at' => $mission->arrival_at,
                    'mission_ends_at' => $mission->mission_ends_at
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Fehler bei der Status-Abfrage:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Ein interner Fehler ist aufgetreten'], 500);
        }
    }
}
