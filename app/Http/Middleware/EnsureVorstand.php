<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureVorstand
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $team = $user?->currentTeam;

        if (
            $team && (
                $team->hasUserWithRole($user, 'Admin') ||
                $team->hasUserWithRole($user, 'Vorstand') ||
                $team->hasUserWithRole($user, 'Kassenwart')
            )
        ) {
            return $next($request);
        }

        abort(403);
    }
}
