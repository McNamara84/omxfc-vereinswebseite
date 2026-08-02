<?php

namespace Tests\Unit;

use App\Services\ErrorReporting\ErrorFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ErrorFingerprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ignores_variable_exception_messages_at_the_same_origin(): void
    {
        $service = app(ErrorFingerprint::class);
        $first = $this->exceptionWithMessage('Datensatz 123 fehlt');
        $second = $this->exceptionWithMessage('Datensatz 999 fehlt');

        $this->assertSame(
            $service->forException($first, 'dashboard', null),
            $service->forException($second, 'dashboard', null),
        );
    }

    public function test_it_distinguishes_routes_and_execution_names(): void
    {
        $service = app(ErrorFingerprint::class);
        $exception = $this->exceptionWithMessage('Fehler');

        $this->assertNotSame(
            $service->forException($exception, 'dashboard', null),
            $service->forException($exception, 'profile.show', null),
        );
        $this->assertNotSame(
            $service->forException($exception, null, 'App\\Jobs\\FirstJob'),
            $service->forException($exception, null, 'App\\Jobs\\SecondJob'),
        );
    }

    private function exceptionWithMessage(string $message): RuntimeException
    {
        return new RuntimeException($message);
    }
}
