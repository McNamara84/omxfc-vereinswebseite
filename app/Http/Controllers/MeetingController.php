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
        $validator = validator($request->all(), [
            'meeting' => ['required', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ])->stopOnFirstFailure();

        if ($validator->fails()) {
            abort(403, 'Unbekanntes Meeting');
        }

        $meeting = Meeting::query()
            ->active()
            ->where('slug', $validator->validated()['meeting'])
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
