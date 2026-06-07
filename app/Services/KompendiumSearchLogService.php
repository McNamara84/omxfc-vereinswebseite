<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\KompendiumSearchLog;
use App\Models\User;

class KompendiumSearchLogService
{
    public const MAX_QUERY_LENGTH = 255;

    private const MAX_NORMALIZED_QUERY_LENGTH = 255;

    public function __construct(
        private readonly RewardService $rewardService
    ) {}

    /**
     * @param  array{
     *     query?: string|null,
     *     parsed_query?: array|null,
     *     selected_serien?: array|null,
     *     sort?: string|null,
     *     direction?: string|null,
     *     results_count?: int|null,
     *     source?: string|null,
     *     status?: string|null,
     *     candidates_truncated?: bool|null,
     *     scanned_candidates?: int|null
     * }  $payload
     */
    public function record(?User $user, array $payload): ?KompendiumSearchLog
    {
        if (! $user || ! $this->hasKompendiumAccess($user)) {
            return null;
        }

        $query = trim((string) ($payload['query'] ?? ''));

        if ($query === '' || mb_strlen($query) < 2 || mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            return null;
        }

        return KompendiumSearchLog::query()->create([
            'user_id' => $user->id,
            'query' => $query,
            'normalized_query' => $this->normalizeQuery($query),
            'parsed_query' => $this->normalizeArray($payload['parsed_query'] ?? null),
            'selected_serien' => $this->normalizeArray($payload['selected_serien'] ?? null),
            'sort' => $this->limit((string) ($payload['sort'] ?? 'relevance'), 50),
            'direction' => $this->limit((string) ($payload['direction'] ?? 'desc'), 20),
            'results_count' => max(0, (int) ($payload['results_count'] ?? 0)),
            'source' => $this->limit((string) ($payload['source'] ?? 'search_submit'), 50),
            'status' => $this->limit((string) ($payload['status'] ?? 'ok'), 50),
            'is_admin_search' => $this->isAdmin($user),
            'candidates_truncated' => (bool) ($payload['candidates_truncated'] ?? false),
            'scanned_candidates' => isset($payload['scanned_candidates'])
                ? max(0, (int) $payload['scanned_candidates'])
                : null,
        ]);
    }

    public function normalizeQuery(string $query): string
    {
        $normalized = mb_strtolower(trim($query));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $this->limit($normalized, self::MAX_NORMALIZED_QUERY_LENGTH);
    }

    private function hasKompendiumAccess(User $user): bool
    {
        if ($user->isMemberOfTeam('AG Maddraxikon')) {
            return true;
        }

        return $this->rewardService->hasUnlockedReward($user, 'kompendium');
    }

    private function isAdmin(User $user): bool
    {
        return $user->currentTeam?->hasUserWithRole($user, Role::Admin->value) ?? false;
    }

    private function limit(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }

    private function normalizeArray(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        return $value;
    }
}
