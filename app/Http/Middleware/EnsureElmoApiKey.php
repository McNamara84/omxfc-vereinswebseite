<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureElmoApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = (string) config('elmo.api_key', '');

        if ($expectedKey === '') {
            return response()->json([
                'message' => 'ELMO API key is not configured.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $providedKey = $this->extractApiKey($request);

        if ($providedKey === null) {
            return response()->json([
                'message' => 'Missing API key.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! hash_equals($expectedKey, $providedKey)) {
            return response()->json([
                'message' => 'Invalid API key.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }

    private function extractApiKey(Request $request): ?string
    {
        $candidates = array_filter([
            $request->header('X-API-KEY'),
            $request->header('X_API_KEY'),
            $request->query('api_key'),
            $request->bearerToken(),
        ]);

        if (empty($candidates)) {
            return null;
        }

        return (string) $candidates[0];
    }
}
