<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KompendiumSearchLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait KompendiumSearchAnalyticsDashboardTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function createLog(User $user, string $query, array $overrides = []): KompendiumSearchLog
    {
        return KompendiumSearchLog::query()->create(array_merge(['user_id' => $user->id, 'query' => $query, 'normalized_query' => mb_strtolower($query), 'parsed_query' => ['terms' => [mb_strtolower($query)]], 'selected_serien' => ['maddrax'], 'sort' => 'relevance', 'direction' => 'desc', 'results_count' => 3, 'source' => 'search_submit', 'status' => 'ok', 'is_admin_search' => false, 'candidates_truncated' => false, 'created_at' => now(), 'updated_at' => now()], $overrides));
    }
}
