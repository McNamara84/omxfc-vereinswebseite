<?php

namespace App\Http\Controllers;

use App\Http\Requests\ThreeDModelRequest;
use App\Models\ThreeDModel;
use App\Services\TeamPointService;
use App\Services\ThreeDModelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ThreeDModelController extends Controller
{
    public function __construct(
        private readonly ThreeDModelService $threeDModelService,
        private readonly TeamPointService $teamPointService,
    ) {}

    /**
     * Übersicht aller 3D-Modelle.
     */
    public function index(): View
    {
        $models = ThreeDModel::orderByDesc('created_at')->get();
        $userPoints = $this->teamPointService->getUserPoints(Auth::user());

        return view('three-d-models.index', [
            'models' => $models,
            'userPoints' => $userPoints,
        ]);
    }

    /**
     * Detailseite eines 3D-Modells mit Viewer.
     */
    public function show(ThreeDModel $threeDModel): View
    {
        $userPoints = $this->teamPointService->getUserPoints(Auth::user());
        $isUnlocked = $userPoints >= $threeDModel->required_baxx;

        return view('three-d-models.show', [
            'model' => $threeDModel,
            'userPoints' => $userPoints,
            'isUnlocked' => $isUnlocked,
        ]);
    }

    /**
     * Upload-Formular anzeigen (Admin/Vorstand).
     */
    public function create(): View
    {
        return view('three-d-models.create');
    }

    /**
     * Neues 3D-Modell speichern (Admin/Vorstand).
     */
    public function store(ThreeDModelRequest $request): RedirectResponse
    {
        $this->threeDModelService->storeModel(
            file: $request->file('model_file'),
            metadata: [
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'required_baxx' => $request->validated('required_baxx'),
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
        return view('three-d-models.edit', ['model' => $threeDModel]);
    }

    /**
     * Bestehendes 3D-Modell aktualisieren (Admin/Vorstand).
     */
    public function update(ThreeDModelRequest $request, ThreeDModel $threeDModel): RedirectResponse
    {
        $this->threeDModelService->updateModel(
            model: $threeDModel,
            metadata: [
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'required_baxx' => $request->validated('required_baxx'),
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
        $this->threeDModelService->deleteModel($threeDModel);

        return redirect()->route('3d-modelle.index')
            ->with('success', '3D-Modell erfolgreich gelöscht.');
    }

    /**
     * 3D-Datei herunterladen (Baxx-geschützt).
     */
    public function download(ThreeDModel $threeDModel): StreamedResponse
    {
        $this->teamPointService->assertMinPoints($threeDModel->required_baxx);

        $filename = $threeDModel->name.'.'.$threeDModel->file_format;

        return Storage::disk('private')->download($threeDModel->file_path, $filename);
    }

    /**
     * 3D-Datei für Three.js Viewer streamen (Baxx-geschützt).
     */
    public function preview(ThreeDModel $threeDModel): StreamedResponse
    {
        $this->teamPointService->assertMinPoints($threeDModel->required_baxx);

        return Storage::disk('private')->response($threeDModel->file_path);
    }
}
