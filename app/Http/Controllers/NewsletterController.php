<?php

namespace App\Http\Controllers;

use App\Enums\NewsletterAusgabeStatus;
use App\Enums\Role;
use App\Mail\Newsletter;
use App\Models\NewsletterAusgabe;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class NewsletterController extends Controller
{
    /**
     * Default role pre-selected on the form. Members are the usual audience.
     */
    private const DEFAULT_ROLE = Role::Mitglied;

    /**
     * Display the newsletter form.
     */
    public function create()
    {
        $user = Auth::user();
        $team = $user?->currentTeam;
        if (! $team || ! $team->hasUserWithRole($user, Role::Admin->value)) {
            abort(403);
        }
        $roles = NewsletterAusgabe::recipientRoles();
        $defaultRole = NewsletterAusgabe::defaultRecipientRole();

        return view('newsletter.versenden', compact('roles', 'defaultRole'));
    }

    /**
     * Send the newsletter to the selected roles.
     */
    public function send(Request $request)
    {
        $user = Auth::user();
        $team = $user?->currentTeam;
        if (! $team || ! $team->hasUserWithRole($user, Role::Admin->value)) {
            abort(403);
        }

        $data = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', Rule::in(NewsletterAusgabe::recipientRoleValues())],
            'subject' => ['required', 'string'],
            'topics' => ['required', 'array', 'min:1'],
            'topics.*.title' => ['required', 'string'],
            'topics.*.content' => ['required', 'string'],
        ]);

        $membersTeam = Team::membersTeam();
        if (! $membersTeam) {
            return back()->with('status', 'Team nicht gefunden.');
        }

        $recipients = $membersTeam->users()->wherePivotIn('role', $data['roles'])->get();

        if ($request->boolean('test')) {
            $recipients = $membersTeam->users()->wherePivot('role', Role::Admin->value)->get();

            if ($recipients->isEmpty()) {
                return redirect()->route('newsletter.create')
                    ->with('status', 'Keine Admin-Empfänger gefunden.');
            }
        }

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->queue(new Newsletter($data['subject'], $data['topics']));
        }

        if (! $request->boolean('test')) {
            NewsletterAusgabe::query()->create([
                'subject' => $data['subject'],
                'topics' => $data['topics'],
                'recipient_roles' => $data['roles'],
                'status' => NewsletterAusgabeStatus::Entwurf,
                'sent_at' => now(),
                'created_by' => $user?->id,
            ]);
        }

        return redirect()->route('newsletter.create')->with(
            'status',
            $request->boolean('test') ? 'Newsletter-Test versendet.' : 'Newsletter versendet.'
        );
    }
}
