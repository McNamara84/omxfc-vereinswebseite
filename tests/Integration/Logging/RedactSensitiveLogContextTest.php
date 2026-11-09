<?php

declare(strict_types=1);

namespace Tests\Integration\Logging;

use App\Logging\RedactSensitiveLogContext;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RedactSensitiveLogContextTestFixtures::class);

beforeEach(function (): void {
    $this->logPath = storage_path('logs/redaction-test.log');
    File::delete($this->logPath);
});

afterEach(function (): void {
    Log::forgetChannel('redaction-test');
    File::delete($this->logPath);
});

test('it redacts sensitive context without changing normal log content', function (): void {
    config(['logging.channels.redaction-test' => ['driver' => 'single', 'path' => $this->logPath, 'level' => 'debug', 'replace_placeholders' => true, 'tap' => [RedactSensitiveLogContext::class]]]);
    Log::channel('redaction-test')->warning('Login for {email} failed with {token}.', ['email' => 'member@example.test', 'token' => 'top-secret-token', 'nested' => ['password_confirmation' => 'second-secret'], 'operation' => 'login']);
    $content = File::get($this->logPath);
    expect($content)->toContain('Login for member@example.test failed with [REDACTED].')->toContain('"operation":"login"')->not->toContain('top-secret-token')->not->toContain('second-secret');
});

test('it redacts short sensitive placeholders without masking the same normal value', function (): void {
    config(['logging.channels.redaction-test' => ['driver' => 'single', 'path' => $this->logPath, 'level' => 'debug', 'replace_placeholders' => true, 'tap' => [RedactSensitiveLogContext::class]]]);
    Log::channel('redaction-test')->warning('Request used token {token}; status {status}.', ['token' => '1234', 'status' => '1234']);
    $content = File::get($this->logPath);
    expect($content)->toContain('Request used token [REDACTED]; status 1234.')->toContain('"token":"[REDACTED]"')->toContain('"status":"1234"');
});
