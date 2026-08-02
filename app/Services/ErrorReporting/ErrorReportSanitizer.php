<?php

namespace App\Services\ErrorReporting;

class ErrorReportSanitizer
{
    public function sanitize(string $value): string
    {
        $sanitized = $value;
        $replacements = [
            '#\bBearer\s+[A-Za-z0-9._~+\-/]+=*#i' => 'Bearer [REDACTED]',
            '~\b(Basic\s+)[A-Za-z0-9+/=]+~i' => '$1[REDACTED]',
            '~\b((?:mysql|mariadb|pgsql|postgres|redis|smtp)://[^:\s/@]+:)[^@\s/]+@~i' => '$1[REDACTED]@',
            '~(https?://[^\s?]+)\?[^\s)\]}>"\']+~i' => '$1?[REDACTED]',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $result = preg_replace($pattern, $replacement, $sanitized);

            if (is_string($result)) {
                $sanitized = $result;
            }
        }

        $keys = array_values(array_filter(
            config('error-reporting.sensitive_keys', []),
            fn (mixed $key): bool => is_string($key) && $key !== '',
        ));

        if ($keys !== []) {
            $keyPattern = implode('|', array_map(
                static fn (string $key): string => preg_quote($key, '~'),
                $keys,
            ));

            $result = preg_replace(
                '~(["\']?(?:'.$keyPattern.')["\']?\s*(?::|=>|=)\s*)(?:"[^"]*"|\'[^\']*\'|[^,\s}\]]+)~iu',
                '$1[REDACTED]',
                $sanitized,
            );

            if (is_string($result)) {
                $sanitized = $result;
            }
        }

        return $sanitized;
    }
}
