<?php

namespace Tests\Unit;

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

class ErrorIncidentContextCollectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_collects_sanitized_http_user_role_and_browser_context(): void
    {
        $membersTeam = Team::membersTeam();
        $activeTeam = Team::factory()->create(['name' => 'Arbeitsgruppe']);
        $user = User::factory()->create(['current_team_id' => $activeTeam->id]);
        $membersTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
        $activeTeam->users()->attach($user, ['role' => Role::Vorstand->value]);

        $request = Request::create(
            'https://maddrax-fanclub.de/dashboard?token=secret',
            'POST',
            server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/126.0.6478.57 Safari/537.36'],
        );
        $route = (new Route('POST', '/dashboard', fn () => null))->name('dashboard');
        app()->instance('request', $request);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user->refresh());
        Context::add([
            'correlation_id' => 'request-correlation-id',
            'execution_type' => 'http',
        ]);

        $incident = app(ErrorIncidentContextCollector::class)->collect(
            new RuntimeException('Fehler mit token=top-secret'),
        );

        $this->assertSame('request-correlation-id', $incident->correlationId);
        $this->assertSame('https://maddrax-fanclub.de/dashboard', $incident->url);
        $this->assertSame('dashboard', $incident->route);
        $this->assertSame('POST', $incident->method);
        $this->assertSame($user->id, $incident->userId);
        $this->assertSame($user->email, $incident->userEmail);
        $this->assertSame('Arbeitsgruppe', $incident->activeTeamName);
        $this->assertSame(Role::Vorstand->value, $incident->activeTeamRole);
        $this->assertSame(Role::Mitglied->value, $incident->membersTeamRole);
        $this->assertSame('Google Chrome', $incident->browser);
        $this->assertSame('126.0.6478.57', $incident->browserVersion);
        $this->assertStringNotContainsString('top-secret', $incident->exceptionMessage);
        $this->assertSame($incident->id, Context::get('incident_id'));
    }

    public function test_it_uses_explicit_fallbacks_outside_an_http_request(): void
    {
        Context::add([
            'correlation_id' => 'job-correlation-id',
            'execution_type' => 'queue',
            'execution_name' => 'App\\Jobs\\BrokenJob',
        ]);

        $incident = app(ErrorIncidentContextCollector::class)->collect(new RuntimeException('Queue kaputt'));

        $this->assertSame('queue', $incident->executionType);
        $this->assertSame('App\\Jobs\\BrokenJob', $incident->executionName);
        $this->assertNull($incident->url);
        $this->assertNull($incident->userId);
        $this->assertSame('Gast', $incident->activeTeamRole);
        $this->assertSame('Gast', $incident->membersTeamRole);
        $this->assertSame('Unbekannt', $incident->browser);
        $this->assertSame('Unbekannt', $incident->browserVersion);
    }

    public function test_it_uses_the_request_attribute_when_no_context_id_exists(): void
    {
        Context::flush();
        $request = Request::create('/command-context');
        $request->attributes->set('log_correlation_id', 'request-attribute-id');
        app()->instance('request', $request);

        $incident = app(ErrorIncidentContextCollector::class)->collect(new RuntimeException('Fehler'));

        $this->assertSame('request-attribute-id', $incident->correlationId);
        $this->assertSame('console', $incident->executionType);
    }

    public function test_it_generates_a_correlation_id_when_no_id_is_available(): void
    {
        Context::flush();
        app()->instance('request', Request::create('/command-context'));

        $incident = app(ErrorIncidentContextCollector::class)->collect(new RuntimeException('Fehler'));

        $this->assertTrue(Str::isUuid($incident->correlationId));
    }

    public function test_it_tolerates_broken_route_and_user_resolvers(): void
    {
        $request = Request::create('/broken-context');
        $request->setRouteResolver(fn () => throw new RuntimeException('Route defekt'));
        $request->setUserResolver(fn () => throw new RuntimeException('Auth defekt'));
        app()->instance('request', $request);
        Context::add([
            'correlation_id' => 'broken-context-id',
            'execution_type' => 'http',
        ]);

        $incident = app(ErrorIncidentContextCollector::class)->collect(new RuntimeException('Fehler'));

        $this->assertNull($incident->route);
        $this->assertNull($incident->userId);
        $this->assertSame('Gast', $incident->activeTeamRole);
    }

    public function test_it_limits_large_exception_snapshots(): void
    {
        $incident = app(ErrorIncidentContextCollector::class)->collect(
            new RuntimeException(str_repeat('x', 12000)),
        );

        $this->assertLessThanOrEqual(10000, strlen($incident->exceptionMessage));
        $this->assertStringEndsWith('[Snapshot wegen Größenlimit gekürzt]', $incident->exceptionMessage);
    }
}
