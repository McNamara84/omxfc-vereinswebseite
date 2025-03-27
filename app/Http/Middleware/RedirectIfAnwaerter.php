<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAnwaerter
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $team = $user->currentTeam;

        if ($team && $team->hasUserWithRole($user, 'Anwärter')) {
            auth()->logout();

            return redirect()->route('login')->withErrors('Dein Mitgliedschaftsantrag wird derzeit noch bearbeitet. Wir benachrichtigen dich per E-Mail, sobald du freigeschaltet wurdest.');
        }

        return $next($request);
    }
}
