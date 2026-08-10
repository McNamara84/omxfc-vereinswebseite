<?php

namespace App\Services\Maddraxikon;

use App\Models\MaddraxikonRewardPolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MaddraxikonRewardPolicyPublisher
{
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

            if ($locked->isPublished()) {
                throw ValidationException::withMessages([
                    'policy' => 'Diese Regelversion wurde bereits veröffentlicht.',
                ]);
            }

            if ($locked->effective_from === null || ! $locked->effective_from->gt($now)) {
                throw ValidationException::withMessages([
                    'policyEffectiveFrom' => 'Der Gültigkeitszeitpunkt muss in der Zukunft liegen.',
                ]);
            }

            if ($locked->edit_sessions_enabled && $locked->tiers->isEmpty()) {
                throw ValidationException::withMessages([
                    'policyTiers' => 'Für aktive Bearbeitungssitzungen ist mindestens eine Stufe erforderlich.',
                ]);
            }

            if ($locked->tiers->contains(
                fn ($tier): bool => $tier->minimum_added_bytes < 0 || $tier->points < 0
            )) {
                throw ValidationException::withMessages([
                    'policyTiers' => 'Byte-Grenzen und Baxx müssen nichtnegative Ganzzahlen sein.',
                ]);
            }

            if (
                $locked->new_articles_enabled
                && (
                    $locked->new_article_minimum_bytes === null
                    || $locked->new_article_points === null
                    || $locked->new_article_minimum_bytes < 0
                    || $locked->new_article_points < 0
                )
            ) {
                throw ValidationException::withMessages([
                    'policyNewArticlePoints' => 'Für neue Artikel müssen Mindestgröße und Baxx angegeben sein.',
                ]);
            }

            $duplicate = MaddraxikonRewardPolicy::query()
                ->published()
                ->where('effective_from_epoch', $locked->effective_from->getTimestamp())
                ->whereKeyNot($locked->id)
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'policyEffectiveFrom' => 'Zu diesem Zeitpunkt existiert bereits eine veröffentlichte Version.',
                ]);
            }

            $locked->update([
                'status' => MaddraxikonRewardPolicy::STATUS_PUBLISHED,
                'published_by' => $publisher->id,
                'published_at' => $now,
            ]);

            return $locked->fresh(['tiers', 'creator', 'publisher']);
        }, 3);
    }
}
