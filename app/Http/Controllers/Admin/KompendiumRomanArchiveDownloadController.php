<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\KompendiumRomanArchiveException;
use App\Services\KompendiumRomanArchiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KompendiumRomanArchiveDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        KompendiumRomanArchiveService $archiveService,
    ): BinaryFileResponse|RedirectResponse {
        try {
            $archive = $archiveService->create();
        } catch (KompendiumRomanArchiveException $exception) {
            Log::warning('Kompendium: ZIP-Export wurde abgebrochen.', [
                'user_id' => $request->user()?->id,
                'fehler' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return redirect()
                ->route('kompendium.admin')
                ->with('error', $exception->getMessage());
        }

        return response()
            ->download($archive->path, $archive->downloadName, [
                'Content-Type' => 'application/zip',
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->deleteFileAfterSend(true);
    }
}
