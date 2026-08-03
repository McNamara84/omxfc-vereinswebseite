<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\WebsiteFeedback\FeedbackSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackWebsiteFeedbackSession
{
    public function __construct(private readonly FeedbackSessionService $sessions) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $request->hasSession()) {
            $this->sessions->register($user, $request->session());
        }

        return $next($request);
    }
}
