<?php

namespace App\Http\Middleware;

use App\Services\Maddraxikon\AccountEligibility;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMaddraxikonEligible
{
    public function __construct(private readonly AccountEligibility $eligibility) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user && $this->eligibility->isEligible($user),
            Response::HTTP_FORBIDDEN,
            'Die Maddraxikon-Verknüpfung steht nur aktiven Vereinsmitgliedern zur Verfügung.',
        );

        return $next($request);
    }
}
