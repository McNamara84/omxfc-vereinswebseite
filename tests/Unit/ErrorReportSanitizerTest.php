<?php

namespace Tests\Unit;

use App\Services\ErrorReporting\ErrorReportSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorReportSanitizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_redacts_sensitive_key_values_and_credentials(): void
    {
        $input = <<<'TEXT'
password=super-secret
"token":"abc.def.ghi"
Authorization: Bearer secret-token
Cookie=session=private-cookie
DB mysql://user:db-secret@localhost/database
Basic dXNlcjpwYXNzd29yZA==
TEXT;

        $sanitized = app(ErrorReportSanitizer::class)->sanitize($input);

        $this->assertStringNotContainsString('super-secret', $sanitized);
        $this->assertStringNotContainsString('abc.def.ghi', $sanitized);
        $this->assertStringNotContainsString('secret-token', $sanitized);
        $this->assertStringNotContainsString('private-cookie', $sanitized);
        $this->assertStringNotContainsString('db-secret', $sanitized);
        $this->assertStringNotContainsString('dXNlcjpwYXNzd29yZA==', $sanitized);
        $this->assertGreaterThanOrEqual(6, substr_count($sanitized, '[REDACTED]'));
    }

    public function test_it_removes_url_query_values_but_keeps_the_path(): void
    {
        $sanitized = app(ErrorReportSanitizer::class)->sanitize(
            'GET https://example.test/profile?token=secret&email=user@example.test failed',
        );

        $this->assertStringContainsString('https://example.test/profile?[REDACTED]', $sanitized);
        $this->assertStringNotContainsString('user@example.test', $sanitized);
    }

    public function test_it_leaves_normal_log_content_intact(): void
    {
        $input = '[2026-08-02 14:30:00] production.ERROR: Datei nicht gefunden';

        $this->assertSame($input, app(ErrorReportSanitizer::class)->sanitize($input));
    }
}
