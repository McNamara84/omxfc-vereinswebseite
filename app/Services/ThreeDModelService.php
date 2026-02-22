<?php

namespace App\Services;

use App\Models\ThreeDModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ThreeDModelService
{
    public const ALLOWED_EXTENSIONS = ['stl', 'obj', 'fbx'];

    public const ALLOWED_THUMBNAIL_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const MAX_FILE_SIZE_KB = 102400; // 100 MB

    public const MAX_THUMBNAIL_SIZE_KB = 2048; // 2 MB

    public const MODEL_STORAGE_PATH = '3d-models';

    public const THUMBNAIL_STORAGE_PATH = '3d-thumbnails';

    private const EXTENSION_TO_FORMAT = [
        'stl' => 'stl',
        'obj' => 'obj',
        'fbx' => 'fbx',
    ];

    /**
     * Speichert ein neues 3D-Modell mit Datei und optionalem Thumbnail.
     */
    public function storeModel(UploadedFile $file, array $metadata, ?UploadedFile $thumbnail = null): ThreeDModel
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $uuid = Str::uuid();
        $filename = $uuid.'.'.$extension;

        // 3D-Datei auf private Disk speichern
        $filePath = $file->storeAs(self::MODEL_STORAGE_PATH, $filename, 'private');

        // Thumbnail auf public Disk speichern (optional)
        $thumbnailPath = null;
        if ($thumbnail) {
            $thumbExtension = strtolower($thumbnail->getClientOriginalExtension());
            $thumbFilename = $uuid.'.'.$thumbExtension;
            $thumbnailPath = $thumbnail->storeAs(self::THUMBNAIL_STORAGE_PATH, $thumbFilename, 'public');
        }

        try {
            return DB::transaction(fn () => ThreeDModel::create([
                'name' => $metadata['name'],
                'description' => $metadata['description'],
                'file_path' => $filePath,
                'file_format' => self::EXTENSION_TO_FORMAT[$extension] ?? $extension,
                'file_size' => $file->getSize(),
                'thumbnail_path' => $thumbnailPath,
                'maddraxikon_url' => $metadata['maddraxikon_url'] ?? null,
                'required_baxx' => $metadata['required_baxx'],
                'uploaded_by' => $metadata['uploaded_by'],
            ]));
        } catch (\Throwable $e) {
            // Verwaiste Dateien aufräumen
            Storage::disk('private')->delete($filePath);
            if ($thumbnailPath) {
                Storage::disk('public')->delete($thumbnailPath);
            }

            throw $e;
        }
    }

    /**
     * Aktualisiert ein bestehendes 3D-Modell mit optionaler neuer Datei und Thumbnail.
     */
    public function updateModel(ThreeDModel $model, array $metadata, ?UploadedFile $file = null, ?UploadedFile $thumbnail = null): ThreeDModel
    {
        $oldFilePath = null;
        $oldThumbnailPath = null;

        // Neue 3D-Datei? → Neue speichern, alte merken zum späteren Löschen
        if ($file) {
            $oldFilePath = $model->file_path;

            $extension = strtolower($file->getClientOriginalExtension());
            $uuid = Str::uuid();
            $filename = $uuid.'.'.$extension;

            $model->file_path = $file->storeAs(self::MODEL_STORAGE_PATH, $filename, 'private');
            $model->file_format = self::EXTENSION_TO_FORMAT[$extension] ?? $extension;
            $model->file_size = $file->getSize();
        }

        // Neues Thumbnail? → Neues speichern, altes merken zum späteren Löschen
        if ($thumbnail) {
            $oldThumbnailPath = $model->thumbnail_path;

            $thumbExtension = strtolower($thumbnail->getClientOriginalExtension());
            $thumbFilename = Str::uuid().'.'.$thumbExtension;
            $model->thumbnail_path = $thumbnail->storeAs(self::THUMBNAIL_STORAGE_PATH, $thumbFilename, 'public');
        }

        $model->name = $metadata['name'];
        $model->description = $metadata['description'];
        $model->maddraxikon_url = $metadata['maddraxikon_url'] ?? null;
        $model->required_baxx = $metadata['required_baxx'];
        $model->save();

        // Alte Dateien erst nach erfolgreichem Speichern löschen
        if ($oldFilePath) {
            Storage::disk('private')->delete($oldFilePath);
        }
        if ($oldThumbnailPath) {
            Storage::disk('public')->delete($oldThumbnailPath);
        }

        return $model;
    }

    /**
     * Löscht ein 3D-Modell samt zugehöriger Dateien.
     */
    public function deleteModel(ThreeDModel $model): void
    {
        Storage::disk('private')->delete($model->file_path);

        if ($model->thumbnail_path) {
            Storage::disk('public')->delete($model->thumbnail_path);
        }

        $model->delete();
    }
}
