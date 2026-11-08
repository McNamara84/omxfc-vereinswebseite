<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Providers\FortifyServiceProvider;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

covers(FortifyServiceProvider::class);

test('login rate limiter default behavior', function (): void {
    config(['fortify.disable_login_rate_limit' => false]);
    (new FortifyServiceProvider(app()))->boot();
    $limiter = RateLimiter::limiter('login');
    $request = Request::create('/login', 'POST', [Fortify::username() => 'user@example.com']);
    $request->server->set('REMOTE_ADDR', '127.0.0.1');
    $limit = $limiter($request);
    expect($limit->maxAttempts)->toBe(5);
});

test('login rate limiter can be disabled', function (): void {
    config(['fortify.disable_login_rate_limit' => true]);
    (new FortifyServiceProvider(app()))->boot();
    $limiter = RateLimiter::limiter('login');
    $request = Request::create('/login', 'POST', [Fortify::username() => 'user@example.com']);
    $request->server->set('REMOTE_ADDR', '127.0.0.1');
    $limit = $limiter($request);
    expect($limit)->toBeInstanceOf(Unlimited::class);
    config(['fortify.disable_login_rate_limit' => false]);
});
