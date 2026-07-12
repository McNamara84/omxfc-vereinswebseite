<?php

namespace App\Services\DatabaseMaintenance;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DatabaseMaintenanceAuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $event, array $context = []): void
    {
        $payload = [
            'timestamp' => now()->toIso8601String(),
            'event' => $event,
            'user_id' => Auth::id(),
            'ip' => request()?->ip(),
            'context' => $this->sanitize($context),
        ];

        $path = $this->path();
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($jsonPayload === false) {
            Log::warning('Database maintenance audit entry could not be encoded.', [
                'event' => $event,
                'user_id' => $payload['user_id'],
                'json_error' => json_last_error_msg(),
            ]);

            return;
        }

        File::ensureDirectoryExists(dirname($path), 0775);
        $writtenBytes = @file_put_contents(
            $path,
            $jsonPayload.PHP_EOL,
            FILE_APPEND | LOCK_EX,
        );

        if ($writtenBytes === false) {
            Log::warning('Database maintenance audit entry could not be written.', [
                'path' => $path,
                'event' => $event,
                'user_id' => $payload['user_id'],
            ]);

            return;
        }

        Log::info('Database maintenance: '.$event, $payload);
    }

    public function path(): string
    {
        return rtrim((string) config('database-maintenance.storage_root'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'audit.jsonl';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitize(array $context): array
    {
        $redactedKeys = ['password', 'passwort', 'secret', 'token'];

        return collect($context)
            ->mapWithKeys(function (mixed $value, string $key) use ($redactedKeys): array {
                foreach ($redactedKeys as $redactedKey) {
                    if (str_contains(strtolower($key), $redactedKey)) {
                        return [$key => '[redacted]'];
                    }
                }

                return [$key => $value];
            })
            ->all();
    }
}
