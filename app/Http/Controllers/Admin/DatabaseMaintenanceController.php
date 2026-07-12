<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseMaintenance\DatabaseDumpService;
use App\Services\DatabaseMaintenance\DatabaseMaintenanceException;
use App\Services\DatabaseMaintenance\DatabaseMaintenanceLimitService;
use App\Services\DatabaseMaintenance\DatabaseRestoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseMaintenanceController extends Controller
{
    public function index(DatabaseMaintenanceLimitService $limitService)
    {
        $this->abortIfDisabled();

        return view('admin.datenbank.index', [
            'limits' => $limitService->limits(),
            'lastPreRestoreDump' => $this->lastPreRestoreDump(),
            'confirmationText' => (string) config('database-maintenance.restore_confirmation_text'),
        ]);
    }

    public function dump(Request $request, DatabaseDumpService $dumpService): BinaryFileResponse
    {
        $this->abortIfDisabled();

        try {
            $dumpFile = $dumpService->createDownloadDump($request->user());
        } catch (DatabaseMaintenanceException $exception) {
            throw ValidationException::withMessages([
                'dump' => $exception->getMessage(),
            ]);
        }

        return response()
            ->download($dumpFile->path, $dumpFile->downloadName, ['Content-Type' => 'application/gzip'])
            ->deleteFileAfterSend(true);
    }

    public function restore(
        Request $request,
        DatabaseMaintenanceLimitService $limitService,
        DatabaseRestoreService $restoreService,
    ): RedirectResponse {
        $this->abortIfDisabled();

        $limits = $limitService->limits();
        $maxKilobytes = $this->maxUploadKilobytes($limits);
        $confirmationText = (string) config('database-maintenance.restore_confirmation_text');

        $validated = $request->validate([
            'dump' => [
                'required',
                'file',
                'max:'.$maxKilobytes,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        $fail('Bitte lade eine gültige Datei hoch.');

                        return;
                    }

                    $filename = strtolower($value->getClientOriginalName());

                    if (! str_ends_with($filename, '.sql') && ! str_ends_with($filename, '.sql.gz')) {
                        $fail('Bitte lade eine .sql- oder .sql.gz-Datei hoch.');
                    }
                },
            ],
            'confirmation' => ['required', 'string', Rule::in([$confirmationText])],
        ]);

        try {
            $result = $restoreService->restore($validated['dump'], $request->user());
        } catch (DatabaseMaintenanceException $exception) {
            return back()
                ->withInput($request->except('dump'))
                ->withErrors(['dump' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.datenbank.index')
            ->with('status', 'Datenbank-Dump wurde eingespielt. Vorab-Dump: '.basename($result->preRestoreDumpPath));
    }

    private function abortIfDisabled(): void
    {
        abort_unless((bool) config('database-maintenance.enabled', true), 404);
    }

    /**
     * @param  array<string, mixed>  $limits
     */
    private function maxUploadKilobytes(array $limits): int
    {
        $bytes = $limits['effective_upload_bytes']
            ?? $limits['configured_max_upload_bytes']
            ?? DatabaseMaintenanceLimitService::megabytesToBytes(config('database-maintenance.max_upload_mb'))
            ?? 1024 * 1024;

        return max(1, (int) floor(((int) $bytes) / 1024));
    }

    private function lastPreRestoreDump(): ?array
    {
        $directory = rtrim((string) config('database-maintenance.storage_root'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'pre-restore';

        if (! is_dir($directory)) {
            return null;
        }

        $files = collect(File::files($directory))
            ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.sql.gz'))
            ->sortByDesc(fn ($file): int => $file->getMTime())
            ->values();

        $file = $files->first();

        if (! $file) {
            return null;
        }

        return [
            'filename' => $file->getFilename(),
            'bytes' => $file->getSize(),
            'created_at' => $file->getMTime(),
        ];
    }
}
