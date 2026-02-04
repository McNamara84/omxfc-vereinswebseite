<?php

namespace App\Http\Middleware;

use App\Models\MemberClientSnapshot;
use App\Models\PageVisit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LogPageVisit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (Auth::check()) {
            $userId = Auth::id();
            $userAgent = $request->userAgent();
            $normalizedPath = '/'.ltrim($request->path(), '/');

            PageVisit::create([
                'user_id' => $userId,
                'path' => $normalizedPath,
            ]);

            MemberClientSnapshot::updateOrCreate(
                [
                    'user_id' => $userId,
                    'user_agent_hash' => MemberClientSnapshot::hashUserAgent($userAgent),
                ],
                [
                    'user_agent' => $userAgent,
                    'last_seen_at' => now(),
                ]
            );
        }

        return $response;
    }
}
