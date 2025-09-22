<?php

use App\Providers\FortifyServiceProvider;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Fortify;

test('fortify login limiter keeps default rate limiting when not disabled', function () {
    config(['fortify.disable_login_rate_limit' => false]);

    app(FortifyServiceProvider::class)->boot();

    $limiter = RateLimiter::limiter('login');
    $request = Request::create('/login', 'POST', [Fortify::username() => 'user@example.com']);
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $limit = $limiter($request);

    expect($limit->maxAttempts)->toBe(5);
});

test('fortify login limiter can be disabled through configuration', function () {
    config(['fortify.disable_login_rate_limit' => true]);

    app(FortifyServiceProvider::class)->boot();

    $limiter = RateLimiter::limiter('login');
    $request = Request::create('/login', 'POST', [Fortify::username() => 'user@example.com']);
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $limit = $limiter($request);

    expect($limit)->toBeInstanceOf(Unlimited::class);

    config(['fortify.disable_login_rate_limit' => false]);
});
