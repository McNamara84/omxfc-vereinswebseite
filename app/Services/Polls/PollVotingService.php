<?php

namespace App\Services\Polls;

use App\Enums\PollStatus;
use App\Enums\PollVisibility;
use App\Enums\Role;
use App\Models\Membership;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PollVotingService
{
    public const PUBLIC_VOTE_RATE_LIMIT_MAX_ATTEMPTS = 10;
    public const PUBLIC_VOTE_RATE_LIMIT_DECAY_SECONDS = 60;

    public const ERROR_ALREADY_VOTED_IP = 'Von dieser IP wurde bereits abgestimmt.';

    public function assertPollAcceptsVotes(Poll $poll): void
    {
        if ($poll->status !== PollStatus::Active) {
            throw ValidationException::withMessages([
                'poll' => 'Diese Umfrage ist nicht aktiv.',
            ]);
        }

        if (! $poll->isWithinVotingWindow()) {
            throw ValidationException::withMessages([
                'poll' => 'Diese Umfrage läuft aktuell nicht.',
            ]);
        }
    }

    public function assertMemberEligible(User $user): void
    {
        // Primary membership source is the Jetstream Teams pivot (`team_user.role`).
        // We keep the `memberships` table lookup as a legacy fallback for older data
        // and some fixtures where team assignments might not exist.
        $membersTeam = Team::membersTeam();

        if ($membersTeam) {
            $isInMembersTeam = $membersTeam->users()
                ->where('user_id', $user->id)
                ->wherePivotIn('role', [
                    Role::Mitglied->value,
                    Role::Ehrenmitglied->value,
                    Role::Kassenwart->value,
                    Role::Vorstand->value,
                    Role::Admin->value,
                ])
                ->exists();

            if ($isInMembersTeam) {
                return;
            }
        }

        $hasEligibleMembership = Membership::query()
            ->where('user_id', $user->id)
            ->whereIn('role', [
                Role::Mitglied->value,
                Role::Ehrenmitglied->value,
                Role::Kassenwart->value,
                Role::Vorstand->value,
                Role::Admin->value,
            ])
            ->exists();

        if (! $hasEligibleMembership) {
            throw ValidationException::withMessages([
                'poll' => 'Diese Umfrage ist nur für Vereinsmitglieder verfügbar.',
            ]);
        }
    }

    public function hasAlreadyVoted(Poll $poll, ?User $user, ?string $ipHash): bool
    {
        $query = PollVote::query()->where('poll_id', $poll->id);

        if ($poll->visibility === PollVisibility::Internal) {
            return $user
                ? (clone $query)->where('user_id', $user->id)->exists()
                : false;
        }

        if (! $ipHash) {
            return false;
        }

        // Public polls use a per-IP vote limit (hash stored, not the plain IP).
        // This is an intentional anti-abuse measure. Users behind shared IPs
        // (e.g. corporate NAT/public WiFi) will be treated as one voter.

        return (clone $query)->where('ip_hash', $ipHash)->exists();
    }

    public function vote(Poll $poll, PollOption $option, ?User $user, ?string $ipHash): PollVote
    {
        $this->assertPollAcceptsVotes($poll);

        if ($option->poll_id !== $poll->id) {
            throw ValidationException::withMessages([
                'selectedOptionId' => 'Ungültige Auswahl.',
            ]);
        }

        if ($poll->visibility === PollVisibility::Internal) {
            if (! $user) {
                throw ValidationException::withMessages([
                    'poll' => 'Bitte logge dich ein, um abzustimmen.',
                ]);
            }

            $this->assertMemberEligible($user);

            if ($this->hasAlreadyVoted($poll, $user, null)) {
                throw ValidationException::withMessages([
                    'poll' => 'Du hast bereits an dieser Umfrage teilgenommen.',
                ]);
            }

            return PollVote::create([
                'poll_id' => $poll->id,
                'poll_option_id' => $option->id,
                'user_id' => $user->id,
                'ip_hash' => null,
                'voter_type' => 'member',
            ]);
        }

        if (! $ipHash) {
            throw ValidationException::withMessages([
                'poll' => 'Deine Stimme konnte nicht zugeordnet werden.',
            ]);
        }

        if ($this->hasAlreadyVoted($poll, $user, $ipHash)) {
            throw ValidationException::withMessages([
                'poll' => self::ERROR_ALREADY_VOTED_IP,
            ]);
        }

        $rateKey = sprintf('poll-vote|%d|%s', $poll->id, $ipHash);

        if (RateLimiter::tooManyAttempts($rateKey, self::PUBLIC_VOTE_RATE_LIMIT_MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'poll' => 'Zu viele Versuche. Bitte warte kurz und versuche es erneut.',
            ]);
        }

        RateLimiter::hit($rateKey, self::PUBLIC_VOTE_RATE_LIMIT_DECAY_SECONDS);

        return PollVote::create([
            'poll_id' => $poll->id,
            'poll_option_id' => $option->id,
            'user_id' => $user?->id,
            'ip_hash' => $ipHash,
            'voter_type' => $user ? 'member' : 'guest',
        ]);
    }
}
