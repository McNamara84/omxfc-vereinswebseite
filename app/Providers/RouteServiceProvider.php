<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('reveal-contact-email', function (Request $request) {
            $maxAttempts = (int) config('services.hcaptcha.rate_limit_per_minute', 12);

            if ($maxAttempts <= 0) {
                $maxAttempts = 12;
            }

            return Limit::perMinute($maxAttempts)->by($request->ip());
        });
    }
}
