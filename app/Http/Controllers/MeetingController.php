<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MeetingController extends Controller
{
    public function index()
    {
        $meetings = [
            [
                'name' => 'AG Maddraxikon',
                'day' => 'third monday',
                'time_from' => '20:00',
                'time_to' => '20:30',
            ],
            [
                'name' => 'AG EARDRAX',
                'day' => 'second wednesday',
                'time_from' => '19:00',
                'time_to' => '19:30',
            ],
            [
                'name' => 'AG MAPDRAX',
                'day' => 'first wednesday',
                'time_from' => '20:00',
                'time_to' => '20:30',
            ],
            [
                'name' => 'CHATDRAX 2.0 - Der MADDRAX-Online-Stammtisch',
                'day' => 'see_note',
                'time_from' => '20:00',
                'time_to' => null,
            ],
        ];

        // Nächstes Datum für reguläre Wochentags-Meetings berechnen
        foreach ($meetings as &$meeting) {
            if ($meeting['day'] !== 'see_note') {
                $meeting['next'] = Carbon::parse("{$meeting['day']} of this month")->isFuture()
                    ? Carbon::parse("{$meeting['day']} of this month")
                    : Carbon::parse("{$meeting['day']} of next month");
            } else {
                $meeting['next'] = null;
            }
        }

        return view('pages.meetings', compact('meetings'));
    }

    /**
     * Leitet weiter zum Zoom-Meeting.
     */
    public function redirectToZoom(Request $request)
    {
        $meeting = $request->input('meeting');

        // Mapping von IDs zu echten Zoom-URLs (auf dem Server!)
        $links = [
            'maddraxikon' => env('ZOOM_LINK_MADDRAXIKON'),
            'fanhoerbuch' => env('ZOOM_LINK_HOERBUECHER'),
            'mapdrax' => env('ZOOM_LINK_MAPDRAX'),
            'stammtisch' => env('ZOOM_LINK_STAMMTISCH'),
        ];

        if (! array_key_exists($meeting, $links)) {
            abort(403, 'Unbekanntes Meeting');
        }

        return redirect()->away($links[$meeting]);
    }
}
