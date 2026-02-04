<?php

namespace App\Services;

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

        return is_array($romantauschInfo) ? $romantauschInfo : [];
    }
}
