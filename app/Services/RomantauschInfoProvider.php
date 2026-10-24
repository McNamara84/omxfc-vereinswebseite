<?php

namespace App\Services;

use App\Models\RomantauschBaxxSpecialOffer;
use App\Services\Romantausch\RomantauschBaxxService;
use Illuminate\Support\Facades\Lang;

class RomantauschInfoProvider
{
    /**
     * Retrieve the Romantausch info panel content using configured locale settings.
     */
    public function getInfo(): array
    {
        $locale = config('romantausch.locale', app()->getLocale());
        $fallbackLocale = config('romantausch.fallback_locale');

        if (empty($fallbackLocale) || $fallbackLocale === $locale) {
            $fallbackLocale = config('app.fallback_locale', 'de');
        }

        $romantauschInfo = Lang::get('romantausch.info', [], $locale);

        if (! is_array($romantauschInfo)) {
            $romantauschInfo = Lang::get('romantausch.info', [], $fallbackLocale);
        }

        if (! is_array($romantauschInfo)) {
            return [];
        }
        $baxx = app(RomantauschBaxxService::class);
        $romantauschInfo['reward_rules'] = array_map(static function (string $action) use ($baxx): array {
            $rule = $baxx->getEffectiveRule($action);

            return ['label' => $rule['action_label'], 'description' => $rule['is_active'] && $rule['points'] > 0
                ? $rule['rule_label'].($rule['is_special_offer'] ? ' (Sonderaktion)' : '') : 'Derzeit keine Baxx-Vergabe.'];
        }, RomantauschBaxxSpecialOffer::allowedActionKeys());

        return $romantauschInfo;
    }
}
