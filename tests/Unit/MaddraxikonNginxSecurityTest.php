<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MaddraxikonNginxSecurityTest extends TestCase
{
    public function test_access_log_format_cannot_record_oauth_query_parameters(): void
    {
        $config = file_get_contents(
            dirname(__DIR__, 2).'/deploy/nginx/laravel.conf',
        );

        $this->assertIsString($config);
        $this->assertMatchesRegularExpression(
            '/access_log\s+\/var\/log\/nginx\/access\.log\s+no_query\s*;/',
            $config,
        );
        $this->assertSame(
            1,
            preg_match('/log_format\s+no_query\s+(.*?);/s', $config, $matches),
        );

        $format = $matches[1];

        $this->assertStringContainsString('$request_method $uri $server_protocol', $format);
        $this->assertDoesNotMatchRegularExpression(
            '/\$(?:request(?:_uri)?|args|query_string|http_referer)\b/',
            $format,
        );
    }
}
