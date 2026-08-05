<?php

namespace App\Services;

use App\Models\KompendiumRoman;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

class KompendiumRomanArchiveService
{
    public function __construct(
        private readonly KompendiumRomanFileValidator $fileValidator,
        private readonly KompendiumRomanArchiveWriter $archiveWriter,
    ) {}

    public function create(): KompendiumRomanArchiveFile
    {
        try {
            return $this->createArchive();
        } catch (KompendiumRomanArchiveException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new KompendiumRomanArchiveException(
                'Das ZIP-Archiv konnte nicht erstellt werden.',
                previous: $exception,
            );
        }
    }

    private function createArchive(): KompendiumRomanArchiveFile
    {
        $romane = KompendiumRoman::query()
            ->orderBy('serie')
            ->orderBy('roman_nr')
            ->orderBy('id')
            ->get();

        if ($romane->isEmpty()) {
            throw new KompendiumRomanArchiveException('Es sind keine Romane für den ZIP-Export vorhanden.');
        }

        $disk = Storage::disk('private');
        $entries = [];
        $archivePaths = [];

        foreach ($romane as $roman) {
            if (! $this->fileValidator->hasValidStoragePath($roman)) {
                throw new KompendiumRomanArchiveException(
                    "Die Dateiinformationen für Roman #{$roman->id} sind ungültig. Der Export wurde abgebrochen.",
                );
            }

            if (! $disk->exists($roman->dateipfad)) {
                throw new KompendiumRomanArchiveException(
                    "Die Datei \"{$roman->dateiname}\" fehlt. Der Export wurde abgebrochen.",
                );
            }

            $sourcePath = $disk->path($roman->dateipfad);
            if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
                throw new KompendiumRomanArchiveException(
                    "Die Datei \"{$roman->dateiname}\" kann nicht gelesen werden. Der Export wurde abgebrochen.",
                );
            }

            $seriesDirectory = KompendiumService::SERIEN[$roman->serie];
            if (! $this->hasValidArchiveDirectory($seriesDirectory)) {
                throw new KompendiumRomanArchiveException(
                    "Der Serienname für Roman #{$roman->id} kann nicht sicher exportiert werden.",
                );
            }

            $archivePath = $seriesDirectory.'/'.$roman->dateiname;
            if (array_key_exists($archivePath, $archivePaths)) {
                throw new KompendiumRomanArchiveException(
                    "Der Archivpfad für Roman #{$roman->id} ist nicht eindeutig. Der Export wurde abgebrochen.",
                );
            }

            $archivePaths[$archivePath] = true;
            $entries[] = [
                'sourcePath' => $sourcePath,
                'archivePath' => $archivePath,
            ];
        }

        $temporaryDirectory = $disk->path('temp/kompendium-exports');
        File::ensureDirectoryExists($temporaryDirectory, 0700);

        $temporaryPath = @tempnam($temporaryDirectory, 'romane-');
        if ($temporaryPath === false) {
            throw new KompendiumRomanArchiveException('Das temporäre ZIP-Archiv konnte nicht angelegt werden.');
        }

        try {
            $this->archiveWriter->write($temporaryPath, $entries);
        } catch (Throwable $exception) {
            File::delete($temporaryPath);

            if ($exception instanceof KompendiumRomanArchiveException) {
                throw $exception;
            }

            throw new KompendiumRomanArchiveException(
                'Das ZIP-Archiv konnte nicht erstellt werden.',
                previous: $exception,
            );
        }

        $archiveSize = @filesize($temporaryPath);
        if (! is_file($temporaryPath) || ! is_readable($temporaryPath) || ! is_int($archiveSize) || $archiveSize <= 0) {
            File::delete($temporaryPath);

            throw new KompendiumRomanArchiveException('Das erstellte ZIP-Archiv ist ungültig.');
        }

        return new KompendiumRomanArchiveFile(
            path: $temporaryPath,
            downloadName: 'omxfc-kompendium-romane-'.now()->format('Y-m-d-His').'.zip',
        );
    }

    private function hasValidArchiveDirectory(string $directory): bool
    {
        return $directory !== ''
            && $directory !== '.'
            && $directory !== '..'
            && preg_match('/[\x00-\x1F\x7F\/\\\\]/', $directory) === 0;
    }
}
