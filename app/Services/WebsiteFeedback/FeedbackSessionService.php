<?php

namespace App\Services\WebsiteFeedback;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FeedbackSessionService
{
    public const SESSION_KEY = 'website_feedback.state';

    /**
     * Register the first authenticated request of an active member session.
     */
    public function register(User $user, Session $session): void
    {
        if (! $this->isActiveMember($user)) {
            return;
        }

        $state = $this->state($session);

        if (($state['user_id'] ?? null) === $user->getKey()) {
            return;
        }

        $fingerprint = $this->sessionFingerprint($user, $session);
        $cacheKey = 'website-feedback:session:'.$fingerprint;

        try {
            $sessionCount = Cache::lock($cacheKey.':lock', 10)->block(
                3,
                function () use ($cacheKey, $user): int {
                    $registeredCount = Cache::get($cacheKey);

                    if (is_int($registeredCount) && $registeredCount > 0) {
                        return $registeredCount;
                    }

                    $newCount = $this->incrementSessionCount($user);
                    $ttlMinutes = max(60, ((int) config('session.lifetime', 120)) + 30);

                    Cache::put($cacheKey, $newCount, now()->addMinutes($ttlMinutes));

                    return $newCount;
                },
            );
        } catch (LockTimeoutException $exception) {
            Log::warning('Feedback-Session konnte wegen eines konkurrierenden Requests noch nicht registriert werden.', [
                'user_id' => $user->getKey(),
                'exception_class' => $exception::class,
            ]);

            return;
        } catch (Throwable $exception) {
            report($exception);

            Log::warning('Feedback-Session konnte nicht registriert werden.', [
                'user_id' => $user->getKey(),
                'exception_class' => $exception::class,
            ]);

            return;
        }

        $interval = max(1, (int) config('feedback.session_interval', 1));

        $session->put(self::SESSION_KEY, [
            'user_id' => $user->getKey(),
            'session_count' => $sessionCount,
            'eligible' => (($sessionCount - 1) % $interval) === 0,
            'submitted' => false,
        ]);
    }

    public function isAvailable(User $user, Session $session): bool
    {
        if (! $this->isActiveMember($user)) {
            return false;
        }

        $state = $this->state($session);

        return ($state['user_id'] ?? null) === $user->getKey()
            && ($state['eligible'] ?? false) === true
            && ($state['submitted'] ?? false) !== true;
    }

    public function markSubmitted(User $user, Session $session): void
    {
        $state = $this->state($session);

        if (($state['user_id'] ?? null) !== $user->getKey()) {
            return;
        }

        $state['submitted'] = true;
        $session->put(self::SESSION_KEY, $state);
    }

    public function isActiveMember(User $user): bool
    {
        $role = $user->mitgliederTeamRole();

        return $role instanceof Role && $role !== Role::Anwaerter;
    }

    /**
     * @return array{user_id?: int, session_count?: int, eligible?: bool, submitted?: bool}
     */
    public function state(Session $session): array
    {
        $state = $session->get(self::SESSION_KEY, []);

        return is_array($state) ? $state : [];
    }

    private function incrementSessionCount(User $user): int
    {
        return DB::transaction(function () use ($user): int {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $newCount = ((int) $lockedUser->website_feedback_session_count) + 1;

            User::query()
                ->whereKey($lockedUser->getKey())
                ->update(['website_feedback_session_count' => $newCount]);

            return $newCount;
        });
    }

    private function sessionFingerprint(User $user, Session $session): string
    {
        $key = (string) config('app.key', '');

        if ($key === '') {
            $key = self::class;
        }

        return hash_hmac(
            'sha256',
            $user->getKey().'|'.$session->getId(),
            $key,
        );
    }
}
