<?php

namespace Tests\Unit\Logging;

use App\Logging\RedactSensitiveLogContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RedactSensitiveLogContextTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = storage_path('logs/redaction-test.log');
        File::delete($this->logPath);
    }

    protected function tearDown(): void
    {
        Log::forgetChannel('redaction-test');
        File::delete($this->logPath);

        parent::tearDown();
    }

    public function test_it_redacts_sensitive_context_without_changing_normal_log_content(): void
    {
        config([
            'logging.channels.redaction-test' => [
                'driver' => 'single',
                'path' => $this->logPath,
                'level' => 'debug',
                'replace_placeholders' => true,
                'tap' => [RedactSensitiveLogContext::class],
            ],
        ]);

        Log::channel('redaction-test')->warning(
            'Login for {email} failed with {token}.',
            [
                'email' => 'member@example.test',
                'token' => 'top-secret-token',
                'nested' => ['password_confirmation' => 'second-secret'],
                'operation' => 'login',
            ],
        );

        $content = File::get($this->logPath);

        expect($content)->toContain('Login for member@example.test failed with [REDACTED].')
            ->toContain('"operation":"login"')->not->toContain('top-secret-token')->not->toContain('second-secret');
    }
}
