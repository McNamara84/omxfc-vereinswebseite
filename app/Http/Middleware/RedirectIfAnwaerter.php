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
        if ($request->user() && $request->user()->currentTeam->hasUserWithRole($request->user(), 'Anwärter')) {
            auth()->logout();

            return redirect()->route('login')->withErrors([
                'email' => 'Dein Mitgliedschaftsantrag wird derzeit noch bearbeitet. Bitte warte auf eine E-Mail vom Vorstand. Diese erhältst du nach erfolgreicher Prüfung deines Antrags und nach Zahlungseingang deines Mitgliedsbeitrags für das erste Jahr.'
            ]);
        }

        return $next($request);
    }
}
