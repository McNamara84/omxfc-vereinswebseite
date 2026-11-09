<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\ErrorReporting\ErrorIncidentContextCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('it collects sanitized http user role and browser context', function (): void {
    $membersTeam = Team::membersTeam();
    $activeTeam = Team::factory()->create(['name' => 'Arbeitsgruppe']);
    $user = User::factory()->create(['current_team_id' => $activeTeam->id]);
    $membersTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    $activeTeam->users()->attach($user, ['role' => Role::Vorstand->value]);
    $request = Request::create('https://maddrax-fanclub.de/dashboard?token=secret', 'POST', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/126.0.6478.57 Safari/537.36']);
    $route = (new Route('POST', '/dashboard', fn () => null))->name('dashboard');
    app()->instance('request', $request);
    $request->setRouteResolver(fn () => $route);
    $request->setUserResolver(fn () => $user->refresh());
    Context::add(['correlation_id' => 'request-correlation-id', 'execution_type' => 'http']);
    $incident = app(ErrorIncidentContextCollector::class)->collect(new RuntimeException('Fehler mit token=top-secret'));
    expect($incident->correlationId)->toBe('request-correlation-id')
        ->and($incident->url)->toBe('https://maddrax-fanclub.de/dashboard')
        ->and($incident->route)->toBe('dashboard')
        ->and($incident->method)->toBe('POST')
        ->and($incident->userId)->toBe($user->id)
        ->and($incident->userEmail)->toBe($user->email)
        ->and($incident->activeTeamName)->toBe('Arbeitsgruppe')
        ->and($incident->activeTeamRole)->toBe(Role::Vorstand->value)
        ->and($incident->membersTeamRole)->toBe(Role::Mitglied->value)
        ->and($incident->browser)->toBe('Google Chrome')
        ->and($incident->browserVersion)->toBe('126.0.6478.57')
        ->and($incident->exceptionMessage)->not->toContain('top-secret')
        ->and(Context::get('incident_id'))->toBe($incident->id);
});

test('it uses explicit fallbacks outside an http request', function (): void {
    Context::add(['correlation_id' => 'job-correlation-id', 'execution_type' => 'queue', 'execution_name' => 'App\Jobs\BrokenJob']);
    $incident = app(ErrorIncidentContextCollector::class)->collect(new RuntimeException('Queue kaputt'));
    expect($incident->executionType)->toBe('queue')
        ->and($incident->executionName)->toBe('App\Jobs\BrokenJob')
        ->and($incident->url)->toBeNull()
        ->and($incident->userId)->toBeNull()
        ->and($incident->activeTeamRole)->toBe('Gast')
        ->and($incident->membersTeamRole)->toBe('Gast')
        ->and($incident->browser)->toBe('Unbekannt')
        ->and($incident->browserVersion)->toBe('Unbekannt');
});

test('it uses the request attribute when no context id exists', function (): void {
    Context::flush();
    $request = Request::create('/command-context');
    $request->attributes->set('log_correlation_id', 'request-attribute-id');
    app()->instance('request', $request);
    $incident = app(ErrorIncidentContextCollector::class)->collect(new RuntimeException('Fehler'));
    expect($incident->correlationId)->toBe('request-attribute-id')
        ->and($incident->executionType)->toBe('console');
});

test('it generates a correlation id when no id is available', function (): void {
    Context::flush();
    app()->instance('request', Request::create('/command-context'));
    $incident = app(ErrorIncidentContextCollector::class)->collect(new RuntimeException('Fehler'));
    expect(Str::isUuid($incident->correlationId))->toBeTrue();
});

test('it tolerates broken route and user resolvers', function (): void {
    $request = Request::create('/broken-context');
    $request->setRouteResolver(fn () => throw new RuntimeException('Route defekt'));
    $request->setUserResolver(fn () => throw new RuntimeException('Auth defekt'));
    app()->instance('request', $request);
    Context::add(['correlation_id' => 'broken-context-id', 'execution_type' => 'http']);
    $incident = app(ErrorIncidentContextCollector::class)->collect(new RuntimeException('Fehler'));
    expect($incident->route)->toBeNull()
        ->and($incident->userId)->toBeNull()
        ->and($incident->activeTeamRole)->toBe('Gast');
});

test('it limits large exception snapshots', function (): void {
    $incident = app(ErrorIncidentContextCollector::class)->collect(new RuntimeException(str_repeat('x', 12000)));
    expect(strlen($incident->exceptionMessage))->toBeLessThanOrEqual(10000)
        ->and($incident->exceptionMessage)->toEndWith('[Snapshot wegen Größenlimit gekürzt]');
});
