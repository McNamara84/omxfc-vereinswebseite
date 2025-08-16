<?php

namespace App\Http\Controllers;

use App\Mail\Newsletter;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class NewsletterController extends Controller
{
    /**
     * Roles that can receive newsletters.
     */
    private const ROLES = ['Mitglied', 'Ehrenmitglied', 'Kassenwart', 'Vorstand', 'Admin'];

    /**
     * Default role pre-selected on the form. Members are the usual audience.
     */
    private const DEFAULT_ROLE = 'Mitglied';

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

        $roles = self::ROLES;
        $defaultRole = self::DEFAULT_ROLE;

        return view('newsletter.versenden', compact('roles', 'defaultRole'));
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
            'roles' => ['required', 'array'],
            'roles.*' => ['string', Rule::in(self::ROLES)],
            'subject' => ['required', 'string'],
            'topics' => ['required', 'array', 'min:1'],
            'topics.*.title' => ['required', 'string'],
            'topics.*.content' => ['required', 'string'],
        ]);

        $membersTeam = Team::where('name', 'Mitglieder')->first();
        if (! $membersTeam) {
            return back()->with('status', 'Team nicht gefunden.');
        }

        $recipients = $membersTeam->users()->wherePivotIn('role', $data['roles'])->get();

        if ($request->boolean('test')) {
            $recipients = $membersTeam->users()->wherePivot('role', 'Admin')->get();

            if ($recipients->isEmpty()) {
                return redirect()->route('newsletter.create')
                    ->with('status', 'Keine Admin-Empfänger gefunden.');
            }
        }

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->queue(new Newsletter($data['subject'], $data['topics']));
        }

        return redirect()->route('newsletter.create')->with(
            'status',
            $request->boolean('test') ? 'Newsletter-Test versendet.' : 'Newsletter versendet.'
        );
    }
}

