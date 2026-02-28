<?php

namespace App\Services;

use App\Models\Reward;
use App\Models\RewardPurchase;
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
            return DB::transaction(function () use ($extension, $filePath, $thumbnailPath, $metadata, $file) {
                $model = ThreeDModel::create([
                    'name' => $metadata['name'],
                    'description' => $metadata['description'],
                    'file_path' => $filePath,
                    'file_format' => self::EXTENSION_TO_FORMAT[$extension] ?? $extension,
                    'file_size' => $file->getSize(),
                    'thumbnail_path' => $thumbnailPath,
                    'maddraxikon_url' => $metadata['maddraxikon_url'] ?? null,
                    'uploaded_by' => $metadata['uploaded_by'],
                ]);

                $this->createRewardForModel($model, $metadata['cost_baxx']);

                return $model;
            });
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
        $newFilePath = null;
        $newThumbnailPath = null;

        // Neue 3D-Datei? → Neue speichern, alte merken zum späteren Löschen
        if ($file) {
            $oldFilePath = $model->file_path;

            $extension = strtolower($file->getClientOriginalExtension());
            $uuid = Str::uuid();
            $filename = $uuid.'.'.$extension;

            $newFilePath = $file->storeAs(self::MODEL_STORAGE_PATH, $filename, 'private');
            $model->file_path = $newFilePath;
            $model->file_format = self::EXTENSION_TO_FORMAT[$extension] ?? $extension;
            $model->file_size = $file->getSize();
        }

        // Neues Thumbnail? → Neues speichern, altes merken zum späteren Löschen
        if ($thumbnail) {
            $oldThumbnailPath = $model->thumbnail_path;

            $thumbExtension = strtolower($thumbnail->getClientOriginalExtension());
            $thumbFilename = Str::uuid().'.'.$thumbExtension;
            $newThumbnailPath = $thumbnail->storeAs(self::THUMBNAIL_STORAGE_PATH, $thumbFilename, 'public');
            $model->thumbnail_path = $newThumbnailPath;
        }

        $model->name = $metadata['name'];
        $model->description = $metadata['description'];
        $model->maddraxikon_url = $metadata['maddraxikon_url'] ?? null;

        try {
            DB::transaction(function () use ($model, $metadata) {
                $model->save();
                $this->updateRewardForModel($model, $metadata['cost_baxx']);
            });
        } catch (\Throwable $e) {
            // Neue Dateien aufräumen, da save() fehlgeschlagen ist
            if ($newFilePath) {
                Storage::disk('private')->delete($newFilePath);
            }
            if ($newThumbnailPath) {
                Storage::disk('public')->delete($newThumbnailPath);
            }

            throw $e;
        }

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
     * Löscht ein 3D-Modell samt zugehöriger Dateien und Reward.
     */
    public function deleteModel(ThreeDModel $model): void
    {
        Storage::disk('private')->delete($model->file_path);

        if ($model->thumbnail_path) {
            Storage::disk('public')->delete($model->thumbnail_path);
        }

        $this->deleteRewardForModel($model);

        $model->delete();
    }

    /**
     * Erstellt einen Reward für ein 3D-Modell.
     */
    public function createRewardForModel(ThreeDModel $model, int $costBaxx): Reward
    {
        $baseSlug = Str::slug($model->name);
        $slug = '3d-'.$baseSlug;
        $counter = 2;

        while (Reward::where('slug', $slug)->exists()) {
            $slug = '3d-'.$baseSlug.'-'.$counter;
            $counter++;
        }

        $reward = Reward::create([
            'title' => $model->name,
            'description' => Str::limit($model->description, 200),
            'category' => '3D-Modelle',
            'slug' => $slug,
            'cost_baxx' => $costBaxx,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $model->update(['reward_id' => $reward->id]);

        return $reward;
    }

    /**
     * Aktualisiert den Reward eines 3D-Modells.
     */
    public function updateRewardForModel(ThreeDModel $model, int $costBaxx): void
    {
        $reward = $model->reward;

        if (! $reward) {
            $this->createRewardForModel($model, $costBaxx);

            return;
        }

        $reward->update([
            'title' => $model->name,
            'description' => Str::limit($model->description, 200),
            'cost_baxx' => $costBaxx,
        ]);
    }

    /**
     * Löscht oder deaktiviert den Reward eines 3D-Modells.
     * Deaktiviert statt löschen, wenn aktive Käufe existieren.
     */
    public function deleteRewardForModel(ThreeDModel $model): void
    {
        $reward = $model->reward;

        if (! $reward) {
            return;
        }

        $hasActivePurchases = RewardPurchase::where('reward_id', $reward->id)
            ->active()
            ->exists();

        if ($hasActivePurchases) {
            $reward->update(['is_active' => false]);
        } else {
            $reward->delete();
        }
    }
}
