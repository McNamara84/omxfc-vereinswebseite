<?php

namespace App\Support\Tours;

final readonly class TourDefinition
{
    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<int, string>  $audience
     */
    public function __construct(
        public string $key,
        public int $version,
        public string $title,
        public string $description,
        public bool $selfServiceEnabled,
        public bool $autoAssignOnMemberApproval,
        public array $audience,
        public array $steps,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(string $key, array $config): self
    {
        return new self(
            key: $key,
            version: (int) ($config['version'] ?? 1),
            title: (string) ($config['title'] ?? $key),
            description: (string) ($config['description'] ?? ''),
            selfServiceEnabled: (bool) ($config['self_service_enabled'] ?? false),
            autoAssignOnMemberApproval: (bool) ($config['auto_assign_on_member_approval'] ?? false),
            audience: array_values(array_filter($config['audience'] ?? [], static fn (mixed $value): bool => is_string($value) && $value !== '')),
            steps: array_values(array_filter($config['steps'] ?? [], static fn (mixed $value): bool => is_array($value))),
        );
    }
}