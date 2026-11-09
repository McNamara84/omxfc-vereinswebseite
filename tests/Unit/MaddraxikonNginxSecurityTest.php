<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

test('access log format cannot record oauth query parameters', function (): void {
    $config = file_get_contents(dirname(__DIR__, 2).'/deploy/nginx/laravel.conf');
    expect($config)->toBeString()
        ->toMatch('/access_log\s+\/var\/log\/nginx\/access\.log\s+no_query\s*;/')
        ->and(preg_match('/log_format\s+no_query\s+(.*?);/s', $config, $matches))->toBe(1);
    $format = $matches[1];
    expect($format)->toContain('$request_method $uri $server_protocol');
    $this->assertDoesNotMatchRegularExpression('/\$(?:request(?:_uri)?|args|query_string|http_referer)\b/', $format);
});
