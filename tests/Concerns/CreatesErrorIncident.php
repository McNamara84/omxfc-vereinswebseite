<?php

namespace Tests\Concerns;

use App\Data\ErrorReporting\ErrorIncident;

trait CreatesErrorIncident
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function errorIncident(array $overrides = []): ErrorIncident
    {
        $values = array_merge([
            'id' => '11111111-1111-4111-8111-111111111111',
            'correlationId' => '22222222-2222-4222-8222-222222222222',
            'occurredAt' => '2026-08-02T14:30:00+02:00',
            'fingerprint' => str_repeat('a', 64),
            'exceptionClass' => \RuntimeException::class,
            'exceptionMessage' => 'Geplanter Testfehler',
            'exceptionFile' => '/var/www/html/app/Test.php',
            'exceptionLine' => 42,
            'exceptionTrace' => '#0 /var/www/html/app/Test.php(42): test()',
            'environment' => 'production',
            'applicationVersion' => '1.2.3',
            'executionType' => 'http',
            'executionName' => null,
            'url' => 'https://maddrax-fanclub.de/dashboard',
            'route' => 'dashboard',
            'method' => 'GET',
            'userId' => 7,
            'userName' => 'Test Admin',
            'userEmail' => 'admin@example.com',
            'activeTeamName' => 'Mitglieder',
            'activeTeamRole' => 'Admin',
            'membersTeamRole' => 'Admin',
            'browser' => 'Google Chrome',
            'browserVersion' => '126.0.0.0',
            'suppressedOccurrences' => 0,
        ], $overrides);

        return new ErrorIncident(...$values);
    }
}
