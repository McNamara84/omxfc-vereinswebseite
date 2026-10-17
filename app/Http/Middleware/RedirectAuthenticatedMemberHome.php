<?php

namespace App\Http\Middleware;

use App\Support\Navigation\NavigationContext;
use Closure;
use Illuminate\Http\Request;
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
        if ($request->query('ansicht') === 'oeffentlich'
            || ! app(NavigationContext::class)->canAccessMembers($request->user())) {
            return $next($request);
        }

        return redirect()->route('dashboard');
    }
}
