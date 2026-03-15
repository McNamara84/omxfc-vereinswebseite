<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminOrVorstand;
use App\Http\Middleware\EnsureHoerbuchAccess;
use App\Http\Middleware\EnsureHoerbuchManage;
use App\Http\Middleware\EnsureVorstand;
use App\Http\Middleware\EnsureVorstandOrKassenwart;
use App\Http\Middleware\LogPageVisit;
use App\Http\Middleware\RedirectIfAnwaerter;
use App\Http\Middleware\UpdateLastActivity;
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
            'redirect.if.anwaerter' => RedirectIfAnwaerter::class,
            'admin' => EnsureAdmin::class,
            'vorstand' => EnsureVorstand::class,
            'admin-or-vorstand' => EnsureAdminOrVorstand::class,
            'vorstand-or-kassenwart' => EnsureVorstandOrKassenwart::class,
            'hoerbuch-access' => EnsureHoerbuchAccess::class,
            'hoerbuch-manage' => EnsureHoerbuchManage::class,
        ]);
        $middleware->appendToGroup('web', UpdateLastActivity::class);
        $middleware->appendToGroup('web', LogPageVisit::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
