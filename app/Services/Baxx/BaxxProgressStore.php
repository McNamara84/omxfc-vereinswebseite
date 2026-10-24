<?php

namespace App\Services\Baxx;

use App\Models\BaxxEarningProgress;

final class BaxxProgressStore
{
    /** Caller owns the transaction and the source's lock order. */
    public function lock(int $userId, string $actionKey, int $initialCount): BaxxEarningProgress
    {
        $timestamp = now();
        BaxxEarningProgress::query()->upsert([[
            'user_id' => $userId,
            'action_key' => $actionKey,
            'processed_count' => max(0, $initialCount),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]], ['user_id', 'action_key'], ['updated_at']);

        return BaxxEarningProgress::query()->where('user_id', $userId)
            ->where('action_key', $actionKey)->lockForUpdate()->firstOrFail();
    }

    public function advance(BaxxEarningProgress $progress, int $count): void
    {
        if ($count > $progress->processed_count) {
            $progress->update(['processed_count' => $count]);
        }
    }
}
