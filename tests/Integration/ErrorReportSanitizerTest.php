<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ErrorReporting\ErrorReportSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('it redacts sensitive key values and credentials', function (): void {
    $input = <<<'TEXT'
    password=super-secret
    "token":"abc.def.ghi"
    Authorization: Bearer secret-token
    Cookie=session=private-cookie
    DB mysql://user:db-secret@localhost/database
    Basic dXNlcjpwYXNzd29yZA==
    TEXT;
    $sanitized = app(ErrorReportSanitizer::class)->sanitize($input);
    expect($sanitized)->not->toContain('super-secret')->not->toContain('abc.def.ghi')->not->toContain('secret-token')->not->toContain('private-cookie')->not->toContain('db-secret')->not->toContain('dXNlcjpwYXNzd29yZA==')
        ->and(substr_count($sanitized, '[REDACTED]'))->toBeGreaterThanOrEqual(6);
});

test('it removes url query values but keeps the path', function (): void {
    $sanitized = app(ErrorReportSanitizer::class)->sanitize('GET https://example.test/profile?token=secret&email=user@example.test failed');
    expect($sanitized)->toContain('https://example.test/profile?[REDACTED]')->not->toContain('user@example.test');
});

test('it leaves normal log content intact', function (): void {
    $input = '[2026-08-02 14:30:00] production.ERROR: Datei nicht gefunden';
    expect(app(ErrorReportSanitizer::class)->sanitize($input))->toBe($input);
});
