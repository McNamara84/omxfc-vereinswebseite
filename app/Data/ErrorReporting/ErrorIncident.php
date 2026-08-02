<?php

namespace App\Data\ErrorReporting;

final readonly class ErrorIncident
{
    public function __construct(
        public string $id,
        public string $correlationId,
        public string $occurredAt,
        public string $fingerprint,
        public string $exceptionClass,
        public string $exceptionMessage,
        public string $exceptionFile,
        public int $exceptionLine,
        public string $exceptionTrace,
        public string $environment,
        public string $applicationVersion,
        public string $executionType,
        public ?string $executionName,
        public ?string $url,
        public ?string $route,
        public ?string $method,
        public ?int $userId,
        public ?string $userName,
        public ?string $userEmail,
        public string $activeTeamName,
        public string $activeTeamRole,
        public string $membersTeamRole,
        public string $browser,
        public string $browserVersion,
        public int $suppressedOccurrences = 0,
    ) {}

    public function withSuppressedOccurrences(int $suppressedOccurrences): self
    {
        return new self(
            id: $this->id,
            correlationId: $this->correlationId,
            occurredAt: $this->occurredAt,
            fingerprint: $this->fingerprint,
            exceptionClass: $this->exceptionClass,
            exceptionMessage: $this->exceptionMessage,
            exceptionFile: $this->exceptionFile,
            exceptionLine: $this->exceptionLine,
            exceptionTrace: $this->exceptionTrace,
            environment: $this->environment,
            applicationVersion: $this->applicationVersion,
            executionType: $this->executionType,
            executionName: $this->executionName,
            url: $this->url,
            route: $this->route,
            method: $this->method,
            userId: $this->userId,
            userName: $this->userName,
            userEmail: $this->userEmail,
            activeTeamName: $this->activeTeamName,
            activeTeamRole: $this->activeTeamRole,
            membersTeamRole: $this->membersTeamRole,
            browser: $this->browser,
            browserVersion: $this->browserVersion,
            suppressedOccurrences: max(0, $suppressedOccurrences),
        );
    }
}
