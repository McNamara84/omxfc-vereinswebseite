<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignLogCorrelationId
{
    public const ATTRIBUTE = 'log_correlation_id';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = (string) Str::uuid();

        $request->attributes->set(self::ATTRIBUTE, $correlationId);
        Context::add([
            'correlation_id' => $correlationId,
            'execution_type' => 'http',
        ]);

        $response = $next($request);
        $header = config('error-reporting.response_header');

        if (is_string($header) && $header !== '') {
            $response->headers->set($header, $correlationId);
        }

        return $response;
    }
}
