<?php

namespace App\Services\Dashboard;

use App\Models\Activity;
use App\Models\AdminMessage;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Fanfiction;
use App\Models\FanfictionComment;
use App\Models\FantreffenAnmeldung;
use App\Models\MaddraxikonAccountLink;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\RewardPurchase;
use App\Models\Todo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class DashboardActivityQuery
{
    public const DEFAULT_FILTER = 'all';

    public const PAGE_SIZE = 15;

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        return [
            'all' => 'Alle',
            'content' => 'Beiträge',
            'swap' => 'Tauschbörse',
            'baxx' => 'Baxx & Challenges',
            'club' => 'Verein & Veranstaltungen',
        ];
    }

    public function validFilter(string $filter): string
    {
        return array_key_exists($filter, $this->filters()) ? $filter : self::DEFAULT_FILTER;
    }

    /**
     * @return array{activities: Collection<int, Activity>, nextCursor: ?string, hasMore: bool}
     */
    public function page(string $filter, ?string $cursor = null, int $perPage = self::PAGE_SIZE): array
    {
        $query = Activity::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $this->applyFilter($query, $this->validFilter($filter));

        if ($decodedCursor = $this->decodeCursor($cursor)) {
            $query->where(function (Builder $query) use ($decodedCursor): void {
                $query->where('created_at', '<', $decodedCursor['createdAt'])
                    ->orWhere(function (Builder $sameTimestamp) use ($decodedCursor): void {
                        $sameTimestamp
                            ->where('created_at', $decodedCursor['createdAt'])
                            ->where('id', '<', $decodedCursor['id']);
                    });
            });
        }

        $activities = $query->limit($perPage + 1)->get();
        $hasMore = $activities->count() > $perPage;
        $activities = $activities->take($perPage)->values();
        $last = $activities->last();

        return [
            'activities' => $activities,
            'nextCursor' => $hasMore && $last ? $this->encodeCursor($last) : null,
            'hasMore' => $hasMore,
        ];
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Activity>
     */
    public function findMany(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        $positions = array_flip($ids);
        $activities = Activity::query()
            ->with(['user', 'subject'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Activity $activity): int => $positions[$activity->id] ?? PHP_INT_MAX)
            ->values();
        $this->loadMorphRelations($activities);

        return $activities;
    }

    private function applyFilter(Builder $query, string $filter): void
    {
        match ($filter) {
            'content' => $query->whereIn('subject_type', [
                Review::class,
                ReviewComment::class,
                Fanfiction::class,
                FanfictionComment::class,
            ]),
            'swap' => $query->whereIn('subject_type', [BookOffer::class, BookRequest::class, BookSwap::class]),
            'baxx' => $query->where(function (Builder $query): void {
                $query->whereIn('subject_type', [Todo::class, RewardPurchase::class, MaddraxikonAccountLink::class])
                    ->orWhere(function (Builder $userActivities): void {
                        $userActivities
                            ->where('subject_type', User::class)
                            ->where(function (Builder $actions): void {
                                $actions
                                    ->where('action', 'like', 'baxx_milestone_reached_%')
                                    ->orWhere('action', 'like', Activity::ACTION_MADDRAXIKON_BAXX_AWARDED_PREFIX.'%');
                            });
                    });
            }),
            'club' => $query->where(function (Builder $query): void {
                $query->whereIn('subject_type', [AdminMessage::class, FantreffenAnmeldung::class])
                    ->orWhere(function (Builder $members): void {
                        $members->where('subject_type', User::class)
                            ->where('action', 'member_approved');
                    });
            }),
            default => null,
        };
    }

    /**
     * @param  Collection<int, Activity>  $activities
     */
    private function loadMorphRelations(Collection $activities): void
    {
        $activities->loadMorph('subject', [
            BookSwap::class => ['offer.user', 'request.user'],
            Fanfiction::class => ['reward'],
            FanfictionComment::class => ['fanfiction.reward'],
            ReviewComment::class => ['review'],
            RewardPurchase::class => ['reward'],
        ]);
    }

    private function encodeCursor(Activity $activity): string
    {
        $payload = json_encode([
            'created_at' => $activity->created_at->format('Y-m-d H:i:s.u'),
            'id' => $activity->id,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /**
     * @return null|array{createdAt: CarbonImmutable, id: int}
     */
    private function decodeCursor(?string $cursor): ?array
    {
        if (! $cursor) {
            return null;
        }

        try {
            $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
            $payload = json_decode($decoded ?: '', true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($payload) || ! isset($payload['created_at'], $payload['id'])) {
                return null;
            }

            return [
                'createdAt' => CarbonImmutable::parse((string) $payload['created_at']),
                'id' => max(1, (int) $payload['id']),
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
