<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Globale Middleware hier registrieren (für alle Requests)
        $middleware->alias([
            'redirect.if.anwaerter' => \App\Http\Middleware\RedirectIfAnwaerter::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'vorstand' => \App\Http\Middleware\EnsureVorstand::class,
            'admin-or-vorstand' => \App\Http\Middleware\EnsureAdminOrVorstand::class,
            'hoerbuch-access' => \App\Http\Middleware\EnsureHoerbuchAccess::class,
            'hoerbuch-manage' => \App\Http\Middleware\EnsureHoerbuchManage::class,
            'elmo.api' => \App\Http\Middleware\EnsureElmoApiKey::class,
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\UpdateLastActivity::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\LogPageVisit::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
