<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KompendiumRoman;
use App\Services\KompendiumRomanFileValidator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KompendiumRomanDownloadController extends Controller
{
    public function __invoke(
        KompendiumRoman $roman,
        KompendiumRomanFileValidator $fileValidator,
    ): StreamedResponse {
        abort_unless($fileValidator->hasValidStoragePath($roman), 404);

        $disk = Storage::disk('private');

        abort_unless($disk->exists($roman->dateipfad), 404);

        return $disk->download($roman->dateipfad, $roman->dateiname, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
