<?php

namespace App\Services\Romantausch;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

final class MutationLock
{
    /**
     * Every writer locks source owners before offers, requests, swaps and Baxx progress.
     * Matching/completion lock both owners; deletion only changes its owner's source
     * and its swap, so no additional owner lock is acquired after a source lock.
     * Uploads and external effects must stay outside this retryable closure.
     *
     * @template T
     *
     * @param  array<int, int>  $owners
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(array $owners, Closure $callback): mixed
    {
        return DB::transaction(function () use ($owners, $callback) {
            User::whereIn('id', array_unique($owners))->orderBy('id')->lockForUpdate()->get(['id']);

            return $callback();
        }, 3);
    }
}
