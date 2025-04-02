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
                'link' => 'https://fh-potsdam.zoom-x.de/j/67239495040?pwd=E4HHW3KUsEYeaR2mH8iQ28mTOlndYj.1',
            ],
            [
                'name' => 'AG Fanhörbücher',
                'day' => 'second wednesday',
                'time_from' => '19:00',
                'time_to' => '19:30',
                'link' => 'https://fh-potsdam.zoom-x.de/j/66231037369?pwd=87ZTXwcH0bDGfXL03RPxxnQ5zSdFDr.1',
            ],
            [
                'name' => 'AG MAPDRAX',
                'day' => 'first wednesday',
                'time_from' => '20:30',
                'time_to' => '21:00',
                'link' => 'https://fh-potsdam.zoom-x.de/j/61368594487?pwd=9cv8mhDMrHYMGI6t4bdCH3Mbgzo6mQ.1',
            ],
            [
                'name' => 'MADDRAX-Stammtisch',
                'day' => 'see_note',
                'time_from' => '20:00',
                'time_to' => null,
                'link' => 'https://fh-potsdam.zoom-x.de/j/68623833818?pwd=0s93Fwg5o3i936FZbrnqPhE8bkLjuh.1',
            ],
        ];

        // Optional: Nächstes Datum für reguläre Wochentags-Meetings berechnen
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
        'maddraxikon' => 'https://fh-potsdam.zoom-x.de/j/67239495040?pwd=E4HHW3KUsEYeaR2mH8iQ28mTOlndYj.1',
        'fanhoerbuch' => 'https://fh-potsdam.zoom-x.de/j/66231037369?pwd=87ZTXwcH0bDGfXL03RPxxnQ5zSdFDr.1',
        'mapdrax' => 'https://fh-potsdam.zoom-x.de/j/61368594487?pwd=9cv8mhDMrHYMGI6t4bdCH3Mbgzo6mQ.1',
        'stammtisch' => 'https://fh-potsdam.zoom-x.de/j/68623833818?pwd=0s93Fwg5o3i936FZbrnqPhE8bkLjuh.1',
    ];

    if (!array_key_exists($meeting, $links)) {
        abort(403, 'Unbekanntes Meeting');
    }

    return redirect()->away($links[$meeting]);
    }
}
