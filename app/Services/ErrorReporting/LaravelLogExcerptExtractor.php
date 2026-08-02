<?php

namespace App\Services\ErrorReporting;

use App\Data\ErrorReporting\ErrorIncident;
use Carbon\CarbonImmutable;
use Throwable;

class LaravelLogExcerptExtractor
{
    public function __construct(private readonly ErrorReportSanitizer $sanitizer) {}

    public function extract(ErrorIncident $incident): string
    {
        $blocks = [];

        foreach ($this->candidatePaths($incident) as $path) {
            foreach ($this->matchingBlocks($path, $incident) as $block) {
                $blocks[$block] = $block;
            }
        }

        $body = $blocks === []
            ? $this->fallback($incident)
            : implode("\n", array_values($blocks));

        $header = implode("\n", [
            'Bereinigter Laravel-Logauszug',
            'Vorfall-ID: '.$incident->id,
            'Korrelations-ID: '.$incident->correlationId,
            'Zeitpunkt: '.$incident->occurredAt,
            str_repeat('=', 72),
            '',
        ]);

        return $this->limit($header.$this->sanitizer->sanitize($body));
    }

    /**
     * @return array<int, string>
     */
    private function candidatePaths(ErrorIncident $incident): array
    {
        $configuredPath = config('error-reporting.log_path');

        if (! is_string($configuredPath) || $configuredPath === '') {
            return [];
        }

        $paths = [$configuredPath];

        try {
            $date = CarbonImmutable::parse($incident->occurredAt)->format('Y-m-d');
            $pathInfo = pathinfo($configuredPath);
            $directory = ($pathInfo['dirname'] ?? '.') === '.' ? '' : $pathInfo['dirname'].DIRECTORY_SEPARATOR;
            $filename = $pathInfo['filename'] ?? basename($configuredPath);
            $extension = isset($pathInfo['extension']) ? '.'.$pathInfo['extension'] : '';
            $paths[] = $directory.$filename.'-'.$date.$extension;
        } catch (Throwable) {
            // Der unveränderte Basispfad bleibt als Fallback erhalten.
        }

        return array_values(array_unique(array_filter($paths, 'is_file')));
    }

    /**
     * @return array<int, string>
     */
    private function matchingBlocks(string $path, ErrorIncident $incident): array
    {
        $contents = $this->readTail($path);

        if ($contents === '') {
            return [];
        }

        $records = preg_split(
            '/(?=^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] )/m',
            $contents,
            -1,
            PREG_SPLIT_NO_EMPTY,
        );

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', $records),
            fn (string $record): bool => str_contains($record, $incident->id)
                || str_contains($record, $incident->correlationId),
        ));
    }

    private function readTail(string $path): string
    {
        $maxBytes = max(1024, (int) config('error-reporting.max_log_scan_kb', 5120) * 1024);
        $size = @filesize($path);

        if (! is_int($size) || $size <= 0) {
            return '';
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return '';
        }

        try {
            $offset = max(0, $size - $maxBytes);

            if ($offset > 0 && fseek($handle, $offset) !== 0) {
                return '';
            }

            $contents = stream_get_contents($handle);

            if (! is_string($contents)) {
                return '';
            }

            if ($offset > 0 && preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] /m', $contents, $match, PREG_OFFSET_CAPTURE) === 1) {
                $contents = substr($contents, $match[0][1]);
            }

            return $contents;
        } finally {
            fclose($handle);
        }
    }

    private function fallback(ErrorIncident $incident): string
    {
        return implode("\n", [
            'Hinweis: Der zugehörige Laravel-Logblock war nicht verfügbar. Es folgt der beim Reporting gesicherte Exception-Auszug.',
            '',
            $incident->exceptionClass.': '.$incident->exceptionMessage,
            'at '.$incident->exceptionFile.':'.$incident->exceptionLine,
            '[stacktrace]',
            $incident->exceptionTrace,
        ]);
    }

    private function limit(string $contents): string
    {
        $maxBytes = max(1024, (int) config('error-reporting.max_attachment_kb', 512) * 1024);

        if (strlen($contents) <= $maxBytes) {
            return $contents;
        }

        $prefixBytes = min(512, (int) floor($maxBytes / 3));
        $marker = "\n\n[Der Logauszug wurde wegen des konfigurierten Größenlimits in der Mitte gekürzt.]\n\n";
        $availableBytes = max(0, $maxBytes - $prefixBytes - strlen($marker));

        return substr($contents, 0, $prefixBytes).$marker.substr($contents, -$availableBytes);
    }
}
