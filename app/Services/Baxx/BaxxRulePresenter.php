<?php

namespace App\Services\Baxx;

final class BaxxRulePresenter
{
    public function present(BaxxRuleData $rule, string $singular, string $plural, ?string $unitKey = null): array
    {
        $end = $rule->endsAt?->copy()->timezone(config('app.timezone'));
        $data = [
            'points' => $rule->points,
            'every_count' => $rule->everyCount,
            'is_active' => $rule->isActive,
            'is_special_offer' => $rule->isSpecialOffer,
            'rule_label' => $rule->points.' Baxx pro '.($rule->everyCount === 1 ? $singular : $rule->everyCount.' '.$plural),
            'ends_at' => $rule->endsAt,
            'ends_at_formatted' => $end?->format('d.m.Y, H:i'),
        ];

        if ($unitKey !== null) {
            $rate = $rule->points / $rule->everyCount;
            $label = rtrim(rtrim(number_format($rate, 2, ',', ''), '0'), ',');
            $data += [
                'points_per_'.$unitKey => $rate,
                'points_per_'.$unitKey.'_label' => $label,
                'banner_text' => $rule->isSpecialOffer ? 'Sonderaktion: Jetzt für einen begrenzten Zeitraum '.$label.' Baxx pro '.$singular.'!' : null,
                'banner_end_text' => $end ? 'Die Aktion endet am '.$end->format('d.m.Y').' um '.$end->format('H:i').' Uhr.' : null,
            ];
        }

        return $data;
    }
}
