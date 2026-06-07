<?php

namespace App\Services;

use App\Models\KompendiumSearchLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KompendiumSearchAnalyticsService
{
    public function query(array $filters = []): Builder
    {
        $from = $this->parseDate($filters['from'] ?? null, now()->subDays(30)->startOfDay());
        $to = $this->parseDate($filters['to'] ?? null, now()->endOfDay());

        return KompendiumSearchLog::query()
            ->with('user:id,name,email')
            ->whereBetween('created_at', [$from, $to])
            ->when(! ($filters['include_admin_searches'] ?? false), fn (Builder $query) => $query->where('is_admin_search', false))
            ->when(filled($filters['user_id'] ?? null), fn (Builder $query) => $query->where('user_id', (int) $filters['user_id']))
            ->when(filled($filters['source'] ?? null), fn (Builder $query) => $query->where('source', (string) $filters['source']))
            ->when((bool) ($filters['only_zero_results'] ?? false), fn (Builder $query) => $query->where('results_count', 0))
            ->when(filled($filters['term'] ?? null), function (Builder $query) use ($filters): void {
                $term = trim((string) $filters['term']);
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('query', 'like', "%{$term}%")
                        ->orWhere('normalized_query', 'like', "%{$term}%");
                });
            });
    }

    public function summary(array $filters = []): array
    {
        $base = $this->query($filters);
        $total = (clone $base)->count();
        $zeroResults = (clone $base)->where('results_count', 0)->count();
        $uniqueUsers = (clone $base)->whereNotNull('user_id')->distinct('user_id')->count('user_id');
        $filterChanges = (clone $base)->whereIn('source', ['filter_change', 'sort_change'])->count();
        $topTerm = $this->topQueries($filters, 1)->first();

        return [
            'total' => $total,
            'unique_users' => $uniqueUsers,
            'zero_results' => $zeroResults,
            'zero_result_rate' => $total > 0 ? round(($zeroResults / $total) * 100, 1) : 0.0,
            'top_query' => $topTerm['query'] ?? '-',
            'top_query_count' => $topTerm['total'] ?? 0,
            'filter_changes' => $filterChanges,
        ];
    }

    public function recentSearches(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($filters)
            ->latest()
            ->paginate($perPage);
    }

    public function topQueries(array $filters = [], int $limit = 10): Collection
    {
        return $this->query($filters)
            ->select('normalized_query')
            ->selectRaw('MIN(query) as query, COUNT(*) as total, SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) as zero_results')
            ->groupBy('normalized_query')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (KompendiumSearchLog $log) => [
                'query' => (string) $log->query,
                'normalized_query' => (string) $log->normalized_query,
                'total' => (int) $log->total,
                'zero_results' => (int) $log->zero_results,
            ]);
    }

    public function zeroResultQueries(array $filters = [], int $limit = 10): Collection
    {
        $filters['only_zero_results'] = true;

        return $this->topQueries($filters, $limit);
    }

    public function userStats(array $filters = [], int $limit = 10): Collection
    {
        return $this->query($filters)
            ->select('user_id')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) as zero_results, MAX(created_at) as last_search_at')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->loadMissing('user:id,name,email')
            ->map(fn (KompendiumSearchLog $log) => [
                'user' => $log->user,
                'total' => (int) $log->total,
                'zero_results' => (int) $log->zero_results,
                'zero_result_rate' => (int) $log->total > 0 ? round(((int) $log->zero_results / (int) $log->total) * 100, 1) : 0.0,
                'last_search_at' => $log->last_search_at ? Carbon::parse($log->last_search_at) : null,
            ]);
    }

    public function sourceDistribution(array $filters = [], int $limit = 10): Collection
    {
        return $this->query($filters)
            ->select('source')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('source')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (KompendiumSearchLog $log) => [
                'source' => (string) $log->source,
                'label' => $this->sourceLabel((string) $log->source),
                'total' => (int) $log->total,
            ]);
    }

    public function searchesOverTime(array $filters = [], int $limit = 31): Collection
    {
        $driver = DB::connection()->getDriverName();
        $dateExpression = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', created_at)",
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD')",
            default => 'DATE(created_at)',
        };

        return $this->query($filters)
            ->selectRaw("{$dateExpression} as day, COUNT(*) as total")
            ->groupBy('day')
            ->orderBy('day')
            ->limit($limit)
            ->get()
            ->map(fn (KompendiumSearchLog $log) => [
                'day' => (string) $log->day,
                'label' => Carbon::parse((string) $log->day)->format('d.m.'),
                'total' => (int) $log->total,
            ]);
    }

    public function usersForFilter(): Collection
    {
        return User::query()
            ->whereIn('id', KompendiumSearchLog::query()->select('user_id')->whereNotNull('user_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function availableSources(): Collection
    {
        return KompendiumSearchLog::query()
            ->select('source')
            ->distinct()
            ->orderBy('source')
            ->pluck('source')
            ->map(fn (string $source) => [
                'id' => $source,
                'name' => $this->sourceLabel($source),
            ]);
    }

    public function resetAll(): int
    {
        return KompendiumSearchLog::query()->delete();
    }

    public function sourceLabel(string $source): string
    {
        return match ($source) {
            'search_submit' => 'Suchstart',
            'filter_change' => 'Filter geändert',
            'sort_change' => 'Sortierung geändert',
            'api_search' => 'API-Suche',
            default => $source,
        };
    }

    private function parseDate(mixed $value, CarbonInterface $fallback): CarbonInterface
    {
        if (! filled($value)) {
            return $fallback;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
