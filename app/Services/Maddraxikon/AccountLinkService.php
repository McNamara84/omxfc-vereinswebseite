<?php

namespace App\Services\Maddraxikon;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Enums\Role;
use App\Mail\MaddraxikonAccountLinked;
use App\Models\Activity;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonAccountLinkCorrection;
use App\Models\MaddraxikonIdentityTombstone;
use App\Models\Team;
use App\Models\User;
use App\Services\LockedMembersTeamMemberships;
use App\Services\Maddraxikon\Exceptions\AccountLinkConflictException;
use App\Services\Maddraxikon\Exceptions\AccountLinkIneligibleException;
use App\Services\MembersTeamMembershipLock;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use LogicException;
use Throwable;

final class AccountLinkService
{
    public function __construct(
        private readonly MembersTeamMembershipLock $membershipLock,
    ) {}

    public function activate(
        User $user,
        MaddraxikonIdentity $identity,
        string $consentVersion,
        CarbonInterface $consentedAt,
    ): MaddraxikonAccountLink {
        $verifiedAt = now();
        $wikiKey = (string) config('maddraxikon.wiki_key');
        MaddraxikonIdentityTombstone::assertStoredHashKeyVersionsAreConfigured(
            $wikiKey,
        );
        $oauthSubjectHashes = MaddraxikonIdentityTombstone::oauthSubjectHashes(
            $wikiKey,
            $identity->oauthSubject,
        );
        $wikiUserIdHashes = MaddraxikonIdentityTombstone::wikiUserIdHashes(
            $wikiKey,
            $identity->wikiUserId,
        );

        try {
            $link = $this->membershipLock->run(
                [(int) $user->getKey()],
                function (LockedMembersTeamMemberships $memberships) use (
                    $consentVersion, $consentedAt, $identity, $oauthSubjectHashes,
                    $user, $verifiedAt, $wikiKey, $wikiUserIdHashes
                ) {
                    if (! $memberships->isActiveMember((int) $user->getKey())) {
                        throw new AccountLinkIneligibleException;
                    }

                    $existingForUser = MaddraxikonAccountLink::query()
                        ->where('user_id', $user->getKey())
                        ->lockForUpdate()
                        ->first();

                    $claimedIdentities = MaddraxikonAccountLink::query()
                        ->where('wiki_key', $wikiKey)
                        ->where(function ($query) use ($identity): void {
                            $query
                                ->where('oauth_subject', $identity->oauthSubject)
                                ->orWhere('wiki_user_id', $identity->wikiUserId);
                        })
                        ->lockForUpdate()
                        ->get();

                    $retiredIdentityExists = MaddraxikonIdentityTombstone::query()
                        ->where('wiki_key', $wikiKey)
                        ->where(function ($query) use ($oauthSubjectHashes, $wikiUserIdHashes): void {
                            $query
                                ->whereIn('oauth_subject_hash', $oauthSubjectHashes)
                                ->orWhereIn('wiki_user_id_hash', $wikiUserIdHashes);
                        })
                        ->lockForUpdate()
                        ->exists();

                    if ($retiredIdentityExists || $claimedIdentities->contains(
                        fn (MaddraxikonAccountLink $claimed): bool => $claimed->user_id !== (int) $user->getKey()
                    )) {
                        $this->throwLinkConflict(
                            $user, $wikiKey, $oauthSubjectHashes, $wikiUserIdHashes,
                            'identity_already_claimed'
                        );
                    }

                    if (
                        $existingForUser
                        && (
                            $existingForUser->wiki_key !== $wikiKey
                            || ! hash_equals($existingForUser->oauth_subject, $identity->oauthSubject)
                            || $existingForUser->wiki_user_id !== $identity->wikiUserId
                        )
                    ) {
                        $this->throwLinkConflict(
                            $user, $wikiKey, $oauthSubjectHashes, $wikiUserIdHashes,
                            'user_has_different_identity'
                        );
                    }

                    $link = $existingForUser ?? new MaddraxikonAccountLink;

                    $link->forceFill([
                        'user_id' => $user->getKey(),
                        'wiki_key' => $wikiKey,
                        'oauth_subject' => $identity->oauthSubject,
                        'wiki_user_id' => $identity->wikiUserId,
                        'wiki_username' => $identity->wikiUsername,
                        'status' => MaddraxikonAccountLinkStatus::Active,
                        'verification_method' => 'oauth2',
                        'first_verified_at' => $existingForUser?->first_verified_at ?? $verifiedAt,
                        'verified_at' => $verifiedAt,
                        'disconnected_at' => null,
                        'consent_version' => $consentVersion,
                        'consented_at' => $consentedAt,
                    ])->save();

                    $link = $link->fresh();

                    Activity::query()->create([
                        'user_id' => $user->getKey(),
                        'subject_type' => MaddraxikonAccountLink::class,
                        'subject_id' => $link->getKey(),
                        'action' => Activity::ACTION_MADDRAXIKON_ACCOUNT_LINKED,
                    ]);

                    return $link;
                });
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                $this->throwLinkConflict(
                    $user, $wikiKey, $oauthSubjectHashes, $wikiUserIdHashes,
                    'unique_constraint'
                );
            }

            throw $exception;
        }

        try {
            Mail::to($user->email)->queue(new MaddraxikonAccountLinked(
                wikiUsername: $link->wiki_username,
                verifiedAt: $link->verified_at->toImmutable(),
            ));
        } catch (Throwable $exception) {
            // The mail is informational and must never roll back a verified link.
            report($exception);
        }

        return $link;
    }

    public function disconnect(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $link = MaddraxikonAccountLink::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $link || ! $link->isActive()) {
                return false;
            }

            $link->forceFill([
                'status' => MaddraxikonAccountLinkStatus::Disconnected,
                'disconnected_at' => now(),
            ])->save();

            return true;
        }, attempts: 3);
    }

    public function releaseDisconnectedLink(
        User $actor,
        MaddraxikonAccountLink $link,
        string $reason,
    ): MaddraxikonAccountLinkCorrection {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(
                'Für die Korrektur der Maddraxikon-Zuordnung ist eine Begründung erforderlich.'
            );
        }

        if (mb_strlen($reason) > 500) {
            throw new InvalidArgumentException(
                'Die Begründung darf höchstens 500 Zeichen enthalten.'
            );
        }

        $membersTeam = Team::membersTeam();

        if (
            ! $membersTeam
            || ! $membersTeam->hasUserWithRole($actor, Role::Admin->value)
        ) {
            throw new LogicException(
                'Nur Administratoren dürfen eine Maddraxikon-Zuordnung zur Neuverknüpfung freigeben.'
            );
        }

        return $this->membershipLock->run(
            [(int) $actor->getKey(), (int) $link->user_id],
            function (LockedMembersTeamMemberships $memberships) use (
                $actor,
                $link,
                $reason,
            ): MaddraxikonAccountLinkCorrection {
                if (! $memberships->hasRole(
                    (int) $actor->getKey(),
                    Role::Admin,
                )) {
                    throw new LogicException(
                        'Nur Administratoren dürfen eine Maddraxikon-Zuordnung zur Neuverknüpfung freigeben.'
                    );
                }

                $lockedActor = $memberships->user((int) $actor->getKey());

                $lockedLink = MaddraxikonAccountLink::query()
                    ->whereKey($link->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedLink->status !== MaddraxikonAccountLinkStatus::Disconnected
                    || $lockedLink->disconnected_at === null
                ) {
                    throw new LogicException(
                        'Nur eine bereits getrennte Maddraxikon-Verknüpfung kann korrigiert werden.'
                    );
                }

                MaddraxikonIdentityTombstone::retire($lockedLink);

                $correction = new MaddraxikonAccountLinkCorrection;
                $correction->forceFill([
                    'actor_user_id' => $lockedActor->getKey(),
                    'affected_user_id' => $lockedLink->user_id,
                    'released_account_link_id' => $lockedLink->getKey(),
                    'wiki_key' => $lockedLink->wiki_key,
                    'old_oauth_subject_hash' => MaddraxikonIdentityTombstone::oauthSubjectHash(
                        $lockedLink->wiki_key,
                        $lockedLink->oauth_subject,
                    ),
                    'old_wiki_user_id' => $lockedLink->wiki_user_id,
                    'old_wiki_username' => $lockedLink->wiki_username,
                    'reason' => $reason,
                    'corrected_at' => now(),
                ])->save();

                $lockedLink->delete();

                return $correction->fresh();
            });
    }

    /**
     * @param  list<string>  $oauthSubjectHashes
     * @param  list<string>  $wikiUserIdHashes
     */
    private function throwLinkConflict(
        User $user,
        string $wikiKey,
        array $oauthSubjectHashes,
        array $wikiUserIdHashes,
        string $reason,
    ): never {
        Log::warning('Maddraxikon OAuth account-link conflict.', [
            'event' => 'maddraxikon.oauth.account_link_conflict',
            'reason' => $reason,
            'user_id' => (int) $user->getKey(),
            'wiki_key' => $wikiKey,
            'oauth_subject_fingerprint' => $oauthSubjectHashes[0],
            'wiki_user_id_fingerprint' => $wikiUserIdHashes[0],
        ]);

        throw new AccountLinkConflictException;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();

        return in_array((string) $sqlState, ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique constraint');
    }
}
