<?php

namespace App\Services;

use App\Models\KompendiumRoman;

class KompendiumRomanFileValidator
{
    public function hasValidStoragePath(KompendiumRoman $roman): bool
    {
        if (! array_key_exists($roman->serie, KompendiumService::SERIEN)) {
            return false;
        }

        if ($roman->dateiname === '' || ! str_ends_with(strtolower($roman->dateiname), '.txt')) {
            return false;
        }

        if (preg_match('/[\x00-\x1F\x7F\/\\\\]/', $roman->dateiname) !== 0) {
            return false;
        }

        $expectedPath = "romane/{$roman->serie}/{$roman->dateiname}";

        return hash_equals($expectedPath, $roman->dateipfad);
    }
}
