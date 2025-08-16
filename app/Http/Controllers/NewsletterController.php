<?php

namespace App\Http\Controllers;

use App\Mail\Newsletter;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class NewsletterController extends Controller
{
    /**
     * Display the newsletter form.
     */
    public function create()
    {
        $user = Auth::user();
        $team = $user?->currentTeam;
        if (! $team || ! ($team->hasUserWithRole($user, 'Vorstand') || $team->hasUserWithRole($user, 'Admin'))) {
            abort(403);
        }

        $roles = ['Mitglied', 'Ehrenmitglied', 'Kassenwart', 'Vorstand', 'Admin'];

        return view('newsletter.versenden', compact('roles'));
    }

    /**
     * Send the newsletter to the selected roles.
     */
    public function send(Request $request)
    {
        $user = Auth::user();
        $team = $user?->currentTeam;
        if (! $team || ! ($team->hasUserWithRole($user, 'Vorstand') || $team->hasUserWithRole($user, 'Admin'))) {
            abort(403);
        }

        $data = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'in:Mitglied,Ehrenmitglied,Kassenwart,Vorstand,Admin',
            'subject' => 'required|string',
            'topics' => 'required|array|min:1',
            'topics.*.title' => 'required|string',
            'topics.*.content' => 'required|string',
        ]);

        $membersTeam = Team::where('name', 'Mitglieder')->first();
        if (! $membersTeam) {
            return back()->with('status', 'Team nicht gefunden.');
        }

        $recipients = $membersTeam->users()->wherePivotIn('role', $data['roles'])->get();

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->queue(new Newsletter($data['subject'], $data['topics']));
        }

        return redirect()->route('newsletter.create')->with('status', 'Newsletter versendet.');
    }
}

