<?php

namespace App\Services\Maddraxikon;

use App\Models\MaddraxikonRewardPolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MaddraxikonRewardPolicyPublisher
{
    /**
     * @param  array{
     *     name: string,
     *     effective_from: CarbonImmutable,
     *     edit_sessions_enabled: bool,
     *     new_articles_enabled: bool,
     *     new_article_minimum_bytes: int,
     *     new_article_points: int
     * }  $attributes
     * @param  list<array{minimum_added_bytes: int, points: int}>  $tiers
     */
    public function publishDraft(
        ?int $policyId,
        array $attributes,
        array $tiers,
        User $publisher,
        ?CarbonImmutable $now = null,
    ): MaddraxikonRewardPolicy {
        $now ??= CarbonImmutable::now('UTC');

        return DB::transaction(function () use (
            $policyId,
            $attributes,
            $tiers,
            $publisher,
            $now,
        ): MaddraxikonRewardPolicy {
            $policy = $policyId === null
                ? new MaddraxikonRewardPolicy([
                    'created_by' => $publisher->id,
                ])
                : MaddraxikonRewardPolicy::query()
                    ->lockForUpdate()
                    ->findOrFail($policyId);

            if ($policy->isPublished()) {
                throw ValidationException::withMessages([
                    'policy' => 'Diese Regelversion wurde bereits veröffentlicht.',
                ]);
            }

            $policy->fill([
                ...$attributes,
                'status' => MaddraxikonRewardPolicy::STATUS_DRAFT,
            ])->save();
            $policy->tiers()->delete();

            foreach ($tiers as $tier) {
                $policy->tiers()->create($tier);
            }

            $policy->load('tiers');

            return $this->publishLocked($policy, $publisher, $now);
        }, 3);
    }

    public function publish(
        MaddraxikonRewardPolicy $policy,
        User $publisher,
        ?CarbonImmutable $now = null,
    ): MaddraxikonRewardPolicy {
        $now ??= CarbonImmutable::now('UTC');

        return DB::transaction(function () use ($policy, $publisher, $now): MaddraxikonRewardPolicy {
            $locked = MaddraxikonRewardPolicy::query()
                ->with('tiers')
                ->lockForUpdate()
                ->findOrFail($policy->id);

            return $this->publishLocked($locked, $publisher, $now);
        }, 3);
    }

    private function publishLocked(
        MaddraxikonRewardPolicy $policy,
        User $publisher,
        CarbonImmutable $now,
    ): MaddraxikonRewardPolicy {
        if ($policy->isPublished()) {
            throw ValidationException::withMessages([
                'policy' => 'Diese Regelversion wurde bereits veröffentlicht.',
            ]);
        }

        if ($policy->effective_from === null || ! $policy->effective_from->gt($now)) {
            throw ValidationException::withMessages([
                'policyEffectiveFrom' => 'Der Gültigkeitszeitpunkt muss in der Zukunft liegen.',
            ]);
        }

        if ($policy->edit_sessions_enabled && $policy->tiers->isEmpty()) {
            throw ValidationException::withMessages([
                'policyTiers' => 'Für aktive Bearbeitungssitzungen ist mindestens eine Stufe erforderlich.',
            ]);
        }

        if ($policy->tiers->contains(
            fn ($tier): bool => $tier->minimum_added_bytes < 0 || $tier->points < 0
        )) {
            throw ValidationException::withMessages([
                'policyTiers' => 'Byte-Grenzen und Baxx müssen nichtnegative Ganzzahlen sein.',
            ]);
        }

        if (
            $policy->new_articles_enabled
            && (
                $policy->new_article_minimum_bytes === null
                || $policy->new_article_points === null
                || $policy->new_article_minimum_bytes < 0
                || $policy->new_article_points < 0
            )
        ) {
            throw ValidationException::withMessages([
                'policyNewArticlePoints' => 'Für neue Artikel müssen Mindestgröße und Baxx angegeben sein.',
            ]);
        }

        $duplicate = MaddraxikonRewardPolicy::query()
            ->published()
            ->where('effective_from_epoch', $policy->effective_from->getTimestamp())
            ->whereKeyNot($policy->id)
            ->lockForUpdate()
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'policyEffectiveFrom' => 'Zu diesem Zeitpunkt existiert bereits eine veröffentlichte Version.',
            ]);
        }

        $policy->update([
            'status' => MaddraxikonRewardPolicy::STATUS_PUBLISHED,
            'published_by' => $publisher->id,
            'published_at' => $now,
        ]);

        return $policy->fresh(['tiers', 'creator', 'publisher']);
    }
}
