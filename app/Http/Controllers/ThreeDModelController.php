<?php

namespace App\Http\Controllers;

use App\Http\Requests\ThreeDModelRequest;
use App\Models\ThreeDModel;
use App\Services\RewardService;
use App\Services\ThreeDModelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ThreeDModelController extends Controller
{
    public function __construct(
        private readonly ThreeDModelService $threeDModelService,
        private readonly RewardService $rewardService,
    ) {}

    /**
     * Übersicht aller 3D-Modelle.
     */
    public function index(): View
    {
        $user = Auth::user();
        $models = ThreeDModel::with('reward')->orderByDesc('created_at')->get();
        $availableBaxx = $this->rewardService->getAvailableBaxx($user);

        // Einmalig alle freigeschalteten Reward-IDs laden (statt N+1 Queries)
        $unlockedRewardIds = $this->rewardService->getUnlockedRewardIds($user);

        // Modelle ohne Reward gelten als kostenlos/freigeschaltet
        $unlockedModelIds = $models
            ->filter(fn ($model) => ! $model->reward || in_array($model->reward_id, $unlockedRewardIds, true))
            ->pluck('id')
            ->toArray();

        return view('three-d-models.index', [
            'models' => $models,
            'availableBaxx' => $availableBaxx,
            'unlockedModelIds' => $unlockedModelIds,
        ]);
    }

    /**
     * Detailseite eines 3D-Modells mit Viewer.
     */
    public function show(ThreeDModel $threeDModel): View
    {
        $threeDModel->loadMissing(['uploader', 'reward']);
        $user = Auth::user();
        // Kein Reward = kostenlos/freigeschaltet
        $isUnlocked = ! $threeDModel->reward
            || $this->rewardService->hasUnlockedReward($user, $threeDModel->reward->slug);
        $availableBaxx = $this->rewardService->getAvailableBaxx($user);

        return view('three-d-models.show', [
            'model' => $threeDModel,
            'availableBaxx' => $availableBaxx,
            'isUnlocked' => $isUnlocked,
        ]);
    }

    /**
     * Upload-Formular anzeigen (Admin/Vorstand).
     */
    public function create(): View
    {
        $this->authorize('create', ThreeDModel::class);

        return view('three-d-models.create');
    }

    /**
     * Neues 3D-Modell speichern (Admin/Vorstand).
     */
    public function store(ThreeDModelRequest $request): RedirectResponse
    {
        $this->authorize('create', ThreeDModel::class);

        $this->threeDModelService->storeModel(
            file: $request->file('model_file'),
            metadata: [
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'maddraxikon_url' => $request->validated('maddraxikon_url'),
                'cost_baxx' => $request->validated('cost_baxx'),
                'uploaded_by' => Auth::id(),
            ],
            thumbnail: $request->file('thumbnail'),
        );

        return redirect()->route('3d-modelle.index')
            ->with('success', '3D-Modell erfolgreich hochgeladen.');
    }

    /**
     * Bearbeiten-Formular anzeigen (Admin/Vorstand).
     */
    public function edit(ThreeDModel $threeDModel): View
    {
        $this->authorize('update', $threeDModel);

        return view('three-d-models.edit', ['model' => $threeDModel]);
    }

    /**
     * Bestehendes 3D-Modell aktualisieren (Admin/Vorstand).
     */
    public function update(ThreeDModelRequest $request, ThreeDModel $threeDModel): RedirectResponse
    {
        $this->authorize('update', $threeDModel);

        $this->threeDModelService->updateModel(
            model: $threeDModel,
            metadata: [
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'maddraxikon_url' => $request->validated('maddraxikon_url'),
                'cost_baxx' => $request->validated('cost_baxx'),
            ],
            file: $request->file('model_file'),
            thumbnail: $request->file('thumbnail'),
        );

        return redirect()->route('3d-modelle.index')
            ->with('success', '3D-Modell erfolgreich aktualisiert.');
    }

    /**
     * 3D-Modell löschen (Admin/Vorstand).
     */
    public function destroy(ThreeDModel $threeDModel): RedirectResponse
    {
        $this->authorize('delete', $threeDModel);

        $this->threeDModelService->deleteModel($threeDModel);

        return redirect()->route('3d-modelle.index')
            ->with('success', '3D-Modell erfolgreich gelöscht.');
    }

    /**
     * 3D-Datei herunterladen (Reward-geschützt).
     */
    public function download(ThreeDModel $threeDModel): StreamedResponse|RedirectResponse
    {
        $threeDModel->loadMissing('reward');

        if ($threeDModel->reward && ! $this->rewardService->hasUnlockedReward(Auth::user(), $threeDModel->reward->slug)) {
            return redirect()->route('3d-modelle.show', $threeDModel)
                ->withErrors(['reward' => 'Du musst dieses 3D-Modell zuerst freischalten.']);
        }

        if (! Storage::disk('private')->exists($threeDModel->file_path)) {
            return redirect()->route('3d-modelle.show', $threeDModel)
                ->withErrors(['reward' => 'Die 3D-Datei existiert nicht mehr.']);
        }

        $filename = Str::slug($threeDModel->name, '-').'.'.$threeDModel->file_format;

        return Storage::disk('private')->download($threeDModel->file_path, $filename, [
            'Content-Type' => $this->getMimeType($threeDModel->file_format),
        ]);
    }

    /**
     * 3D-Datei für Three.js Viewer streamen (Reward-geschützt).
     */
    public function preview(ThreeDModel $threeDModel): StreamedResponse|RedirectResponse
    {
        $threeDModel->loadMissing('reward');

        if ($threeDModel->reward && ! $this->rewardService->hasUnlockedReward(Auth::user(), $threeDModel->reward->slug)) {
            return redirect()->route('3d-modelle.show', $threeDModel)
                ->withErrors(['reward' => 'Du musst dieses 3D-Modell zuerst freischalten.']);
        }

        if (! Storage::disk('private')->exists($threeDModel->file_path)) {
            abort(404, 'Die 3D-Datei existiert nicht mehr.');
        }

        return Storage::disk('private')->response($threeDModel->file_path, null, [
            'Content-Type' => $this->getMimeType($threeDModel->file_format),
        ]);
    }

    /**
     * 3D-Modell per Baxx-Kauf freischalten.
     */
    public function purchase(ThreeDModel $threeDModel): RedirectResponse
    {
        $threeDModel->loadMissing('reward');

        if (! $threeDModel->reward) {
            return redirect()->route('3d-modelle.show', $threeDModel)
                ->withErrors(['reward' => 'Dieses Modell hat keine zugeordnete Belohnung.']);
        }

        try {
            $this->rewardService->purchaseReward(Auth::user(), $threeDModel->reward);

            return redirect()->route('3d-modelle.show', $threeDModel)
                ->with('success', '3D-Modell erfolgreich für '.$threeDModel->reward->cost_baxx.' Baxx freigeschaltet!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('3d-modelle.show', $threeDModel)
                ->withErrors($e->errors());
        }
    }

    /**
     * MIME-Type für 3D-Dateiformate ermitteln.
     */
    private function getMimeType(string $format): string
    {
        return match ($format) {
            'stl' => 'model/stl',
            'obj' => 'text/plain',
            'fbx' => 'application/octet-stream',
            default => 'application/octet-stream',
        };
    }
}
