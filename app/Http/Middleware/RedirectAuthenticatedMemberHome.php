<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RedirectAuthenticatedMemberHome
{
    /**
     * Keep the public homepage at `/`, but send eligible remembered members
     * straight to their community dashboard.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! $user
            || ! $user->hasVerifiedEmail()
            || ! $user->currentTeam
        ) {
            return $next($request);
        }

        $membersTeamRole = $user->mitgliederTeamRole();

        if (
            ! $membersTeamRole instanceof Role
            || $membersTeamRole === Role::Anwaerter
            || Gate::forUser($user)->denies('access-dashboard')
        ) {
            return $next($request);
        }

        return redirect()->route('dashboard');
    }
}
