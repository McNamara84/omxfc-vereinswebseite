<?php

namespace App\Services\ErrorReporting;

use App\Data\ErrorReporting\ErrorIncident;
use App\Http\Middleware\AssignLogCorrelationId;
use App\Models\User;
use App\Services\BrowserStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Throwable;

class ErrorIncidentContextCollector
{
    public function __construct(
        private readonly BrowserStatsService $browserStats,
        private readonly ErrorFingerprint $fingerprint,
        private readonly ErrorReportSanitizer $sanitizer,
    ) {}

    public function collect(Throwable $exception): ErrorIncident
    {
        $incidentId = (string) Str::uuid();
        $correlationId = $this->correlationId();
        $executionType = $this->executionType();
        $executionName = $this->nullableContextString('execution_name');
        $request = $executionType === 'http' ? $this->request() : null;
        $route = $this->routeName($request);
        $user = $this->requestUser($request);
        $activeTeam = $this->activeTeam($user);

        Context::add([
            'incident_id' => $incidentId,
            'correlation_id' => $correlationId,
        ]);

        return new ErrorIncident(
            id: $incidentId,
            correlationId: $correlationId,
            occurredAt: now()->timezone(config('app.timezone'))->toIso8601String(),
            fingerprint: $this->fingerprint->forException($exception, $route, $executionName),
            exceptionClass: $exception::class,
            exceptionMessage: $this->snapshot($exception->getMessage(), 10000),
            exceptionFile: $exception->getFile(),
            exceptionLine: $exception->getLine(),
            exceptionTrace: $this->snapshot(
                $exception->getTraceAsString(),
                max(1024, (int) config('error-reporting.max_attachment_kb', 512) * 1024),
            ),
            environment: (string) config('app.env', 'production'),
            applicationVersion: (string) config('app.version', 'Unbekannt'),
            executionType: $executionType,
            executionName: $executionName,
            url: $request?->url(),
            route: $route,
            method: $request?->method(),
            userId: $user?->getKey(),
            userName: $user?->name,
            userEmail: $user?->email,
            activeTeamName: $activeTeam['name'],
            activeTeamRole: $activeTeam['role'],
            membersTeamRole: $this->membersTeamRole($user),
            browser: $this->browserName($request),
            browserVersion: $this->browserStats->detectBrowserVersion($request?->userAgent()),
        );
    }

    private function correlationId(): string
    {
        $contextId = $this->nullableContextString('correlation_id');

        if ($contextId !== null) {
            return $contextId;
        }

        $requestId = $this->request()?->attributes->get(AssignLogCorrelationId::ATTRIBUTE);

        return is_string($requestId) && $requestId !== '' ? $requestId : (string) Str::uuid();
    }

    private function executionType(): string
    {
        $executionType = $this->nullableContextString('execution_type');

        if ($executionType !== null) {
            return $executionType;
        }

        return app()->runningInConsole() ? 'console' : 'http';
    }

    private function nullableContextString(string $key): ?string
    {
        $value = Context::get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function request(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        return $request instanceof Request ? $request : null;
    }

    private function routeName(?Request $request): ?string
    {
        try {
            $routeName = $request?->route()?->getName();

            return is_string($routeName) && $routeName !== '' ? $routeName : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function requestUser(?Request $request): ?User
    {
        try {
            $user = $request?->user();

            return $user instanceof User ? $user : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{name: string, role: string}
     */
    private function activeTeam(?User $user): array
    {
        if (! $user) {
            return ['name' => 'Nicht verfügbar', 'role' => 'Gast'];
        }

        try {
            return [
                'name' => $user->currentTeam?->name ?? 'Nicht verfügbar',
                'role' => $user->role()?->value ?? 'Nicht verfügbar',
            ];
        } catch (Throwable) {
            return ['name' => 'Nicht verfügbar', 'role' => 'Nicht verfügbar'];
        }
    }

    private function membersTeamRole(?User $user): string
    {
        if (! $user) {
            return 'Gast';
        }

        try {
            return $user->mitgliederTeamRole()?->value ?? 'Nicht verfügbar';
        } catch (Throwable) {
            return 'Nicht verfügbar';
        }
    }

    private function browserName(?Request $request): string
    {
        return $this->browserStats->detectBrowser($request?->userAgent())['browser'];
    }

    private function snapshot(string $value, int $maxBytes): string
    {
        $sanitized = $this->sanitizer->sanitize($value);

        if (strlen($sanitized) <= $maxBytes) {
            return $sanitized;
        }

        $marker = "\n[Snapshot wegen Größenlimit gekürzt]";

        return substr($sanitized, 0, max(0, $maxBytes - strlen($marker))).$marker;
    }
}
