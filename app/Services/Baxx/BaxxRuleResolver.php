<?php

namespace App\Services\Baxx;

use App\Models\BaxxEarningRule;
use App\Models\MaddraxiversumBaxxSpecialOffer;
use App\Models\ReviewBaxxSpecialOffer;
use App\Models\RomantauschBaxxSpecialOffer;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class BaxxRuleResolver
{
    public function base(string $actionKey): BaxxEarningRule
    {
        $defaults = match ($actionKey) {
            'rezension' => ['label' => 'Rezension-Meilenstein', 'description' => '1 Baxx für jede 10. Rezension eines Mitglieds.', 'points' => 1, 'every_count' => 10, 'is_active' => true],
            'maddraxiversum_mission' => ['label' => 'Maddraxiversum-Mission', 'description' => 'Standard-Baxx für eine abgeschlossene Maddraxiversum-Mission (kann pro Mission überschrieben werden).', 'points' => 5, 'every_count' => 1, 'is_active' => true],
            'cover_rating' => ['label' => 'Cover-Bewertungs-Meilenstein', 'description' => '1 Baxx für je 100 erstmals bewertete unterschiedliche Cover.', 'points' => 1, 'every_count' => 100, 'is_active' => true],
            default => RomantauschBaxxSpecialOffer::defaultRuleDefinition($actionKey),
        };

        return BaxxEarningRule::query()->firstOrCreate(['action_key' => $actionKey], $defaults);
    }

    public function offers(string $actionKey): Builder
    {
        return match ($actionKey) {
            'rezension' => ReviewBaxxSpecialOffer::query(),
            'maddraxiversum_mission' => MaddraxiversumBaxxSpecialOffer::query(),
            default => in_array($actionKey, RomantauschBaxxSpecialOffer::allowedActionKeys(), true)
                ? RomantauschBaxxSpecialOffer::query()->forActionKey($actionKey)
                : throw new InvalidArgumentException('Unbekannte Baxx-Sonderaktion: '.$actionKey),
        };
    }

    public function activeOffer(string $actionKey): ReviewBaxxSpecialOffer|MaddraxiversumBaxxSpecialOffer|RomantauschBaxxSpecialOffer|null
    {
        return $this->offers($actionKey)->currentlyActive()->orderByDesc('ends_at')->orderByDesc('id')->first();
    }

    public function resolve(string $actionKey, bool $includeSpecialOffer = true): BaxxRuleData
    {
        $offer = $includeSpecialOffer && $actionKey !== 'cover_rating' ? $this->activeOffer($actionKey) : null;
        $rule = $offer ?? $this->base($actionKey);

        return new BaxxRuleData(
            $actionKey, (int) $rule->points, (int) $rule->every_count,
            (bool) $rule->is_active, $offer !== null, $offer?->ends_at,
            $offer ? null : $rule->description,
        );
    }
}
