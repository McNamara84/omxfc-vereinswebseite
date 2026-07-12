<?php

namespace App\Services\DatabaseMaintenance;

use Illuminate\Support\Facades\File;

class DatabaseMaintenanceLimitService
{
    /**
     * @return array<string, mixed>
     */
    public function limits(): array
    {
        $storageRoot = (string) config('database-maintenance.storage_root');
        File::ensureDirectoryExists($storageRoot, 0775);

        $multipartOverheadBytes = self::megabytesToBytes(config('database-maintenance.multipart_overhead_mb')) ?? 0;
        $uploadMaxBytes = self::parseIniBytes(ini_get('upload_max_filesize'));
        $postMaxBytes = self::parseIniBytes(ini_get('post_max_size'));
        $postPayloadBytes = $postMaxBytes === null ? null : max(0, $postMaxBytes - $multipartOverheadBytes);
        $configuredMaxUploadBytes = self::megabytesToBytes(config('database-maintenance.max_upload_mb'));
        $proxyLimitBytes = self::megabytesToBytes(config('database-maintenance.proxy_limit_mb'));
        $maxUncompressedBytes = self::megabytesToBytes(config('database-maintenance.max_uncompressed_mb')) ?? 0;
        $freeStorageBytes = $this->freeStorageBytes($storageRoot);
        $storageCandidateBytes = $this->storageCandidateBytes($freeStorageBytes);

        $candidates = [
            'php_upload_max_filesize' => $uploadMaxBytes,
            'php_post_max_size' => $postPayloadBytes,
            'app_max_upload' => $configuredMaxUploadBytes,
            'proxy_limit' => $proxyLimitBytes,
            'storage_free_space' => $storageCandidateBytes,
        ];

        $effectiveUploadBytes = collect($candidates)
            ->filter(fn (?int $bytes): bool => $bytes !== null)
            ->min();

        return [
            'effective_upload_bytes' => $effectiveUploadBytes,
            'php_upload_max_filesize_bytes' => $uploadMaxBytes,
            'php_post_max_size_bytes' => $postMaxBytes,
            'php_post_payload_bytes' => $postPayloadBytes,
            'php_memory_limit_bytes' => self::parseIniBytes(ini_get('memory_limit')),
            'php_max_execution_time' => $this->iniInt('max_execution_time'),
            'php_max_input_time' => $this->iniInt('max_input_time'),
            'configured_max_upload_bytes' => $configuredMaxUploadBytes,
            'proxy_limit_bytes' => $proxyLimitBytes,
            'max_uncompressed_bytes' => $maxUncompressedBytes,
            'multipart_overhead_bytes' => $multipartOverheadBytes,
            'storage_root' => $storageRoot,
            'storage_free_bytes' => $freeStorageBytes,
            'storage_candidate_bytes' => $storageCandidateBytes,
            'candidates' => $candidates,
        ];
    }

    public static function parseIniBytes(mixed $value): ?int
    {
        if ($value === false || $value === null) {
            return null;
        }

        $rawValue = trim((string) $value);
        if ($rawValue === '') {
            return null;
        }

        if (is_numeric($rawValue)) {
            $numericValue = (float) $rawValue;

            return $numericValue <= 0 ? null : (int) floor($numericValue);
        }

        if (! preg_match('/^\s*([+-]?\d+(?:\.\d+)?)\s*([kmgtpezy]?)b?\s*$/i', $rawValue, $matches)) {
            return null;
        }

        $number = (float) $matches[1];
        if ($number <= 0) {
            return null;
        }

        $unit = strtolower($matches[2] ?? '');
        $powers = [
            '' => 0,
            'k' => 1,
            'm' => 2,
            'g' => 3,
            't' => 4,
            'p' => 5,
            'e' => 6,
            'z' => 7,
            'y' => 8,
        ];

        return (int) floor($number * (1024 ** ($powers[$unit] ?? 0)));
    }

    public static function megabytesToBytes(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $megabytes = (float) $value;

        return $megabytes <= 0 ? null : (int) floor($megabytes * 1024 * 1024);
    }

    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return 'unbekannt';
        }

        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, $unit === 0 ? 0 : 1, ',', '.').' '.$units[$unit];
    }

    private function freeStorageBytes(string $storageRoot): ?int
    {
        $freeBytes = @disk_free_space($storageRoot);

        return $freeBytes === false ? null : (int) $freeBytes;
    }

    private function storageCandidateBytes(?int $freeStorageBytes): ?int
    {
        if ($freeStorageBytes === null || $freeStorageBytes <= 0) {
            return null;
        }

        $ratio = (float) config('database-maintenance.storage_free_space_ratio', 0.5);
        $ratio = max(0.05, min(1.0, $ratio));

        return (int) floor($freeStorageBytes * $ratio);
    }

    private function iniInt(string $key): ?int
    {
        $value = ini_get($key);

        if ($value === false || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
