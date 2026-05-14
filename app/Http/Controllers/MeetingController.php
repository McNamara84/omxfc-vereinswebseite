<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Services\MeetingScheduleService;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function index(MeetingScheduleService $scheduleService)
    {
        $meetings = Meeting::query()
            ->active()
            ->ordered()
            ->get()
            ->map(function (Meeting $meeting) use ($scheduleService) {
                $meeting->display_rhythm = $scheduleService->describe($meeting);
                $meeting->next_occurrence = $scheduleService->nextOccurrence($meeting);

                return $meeting;
            });

        return view('pages.meetings', compact('meetings'));
    }

    /**
     * Leitet weiter zum Zoom-Meeting.
     */
    public function redirectToZoom(Request $request)
    {
        $meeting = Meeting::query()
            ->active()
            ->where('slug', (string) $request->input('meeting'))
            ->first();

        if (! $meeting) {
            abort(403, 'Unbekanntes Meeting');
        }

        $zoomUrl = $meeting->resolvedZoomUrl();

        if (blank($zoomUrl)) {
            abort(403, 'Für dieses Treffen ist noch kein Zoom-Link hinterlegt.');
        }

        return redirect()->away($zoomUrl);
    }
}
