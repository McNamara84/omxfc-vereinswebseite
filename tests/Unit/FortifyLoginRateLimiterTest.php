<?php

namespace Tests\Unit;

use App\Providers\FortifyServiceProvider;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

class FortifyLoginRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rate_limiter_default_behavior(): void
    {
        config(['fortify.disable_login_rate_limit' => false]);

        (new FortifyServiceProvider(app()))->boot();

        $limiter = RateLimiter::limiter('login');
        $request = Request::create('/login', 'POST', [Fortify::username() => 'user@example.com']);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $limit = $limiter($request);

        $this->assertSame(5, $limit->maxAttempts);
    }

    public function test_login_rate_limiter_can_be_disabled(): void
    {
        config(['fortify.disable_login_rate_limit' => true]);

        (new FortifyServiceProvider(app()))->boot();

        $limiter = RateLimiter::limiter('login');
        $request = Request::create('/login', 'POST', [Fortify::username() => 'user@example.com']);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $limit = $limiter($request);

        $this->assertInstanceOf(Unlimited::class, $limit);

        config(['fortify.disable_login_rate_limit' => false]);
    }
}
