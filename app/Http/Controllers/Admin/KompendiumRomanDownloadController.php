<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KompendiumRoman;
use App\Services\KompendiumService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KompendiumRomanDownloadController extends Controller
{
    public function __invoke(KompendiumRoman $roman): StreamedResponse
    {
        abort_unless($this->hasValidStoragePath($roman), 404);

        $disk = Storage::disk('private');

        abort_unless($disk->exists($roman->dateipfad), 404);

        return $disk->download($roman->dateipfad, $roman->dateiname, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function hasValidStoragePath(KompendiumRoman $roman): bool
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
