<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SecureMaddraxikonOAuthCallbackResponse
{
    public function __construct(
        private readonly ExceptionHandler $exceptions,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            /*
             * Render inside this outer callback boundary so rate-limit and
             * framework error responses cannot bypass the secrecy headers.
             */
            $this->exceptions->report($exception);
            $response = $this->exceptions->render($request, $exception);
        }

        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
