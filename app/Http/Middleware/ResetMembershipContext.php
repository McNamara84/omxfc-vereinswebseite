<?php

namespace App\Http\Middleware;

use App\Services\Memberships\MembershipContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResetMembershipContext
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(MembershipContext::class);
        $context->reset();
        try {
            // Includes Livewire POSTs: a display lookup must never authorize a mutation.
            return $request->isMethodSafe() ? $next($request) : $context->runFresh(fn () => $next($request));
        } finally {
            $context->reset();
        }
    }
}
