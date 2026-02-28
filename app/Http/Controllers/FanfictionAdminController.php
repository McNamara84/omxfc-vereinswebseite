<?php

namespace App\Http\Controllers;

use App\Enums\FanfictionStatus;
use App\Http\Controllers\Concerns\MembersTeamAware;
use App\Http\Requests\FanfictionRequest;
use App\Models\Activity;
use App\Models\BaxxEarningRule;
use App\Models\Fanfiction;
use App\Models\User;
use App\Services\FanfictionService;
use App\Services\UserRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FanfictionAdminController extends Controller
{
    use MembersTeamAware;

    public const ALLOWED_PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const MAX_PHOTOS = 5;

    public const MAX_PHOTO_SIZE_KB = 2048;

    public const PHOTO_STORAGE_PATH = 'fanfiction';

    public function __construct(
        private readonly UserRoleService $userRoleService,
        private readonly FanfictionService $fanfictionService,
    ) {}

    protected function getUserRoleService(): UserRoleService
    {
        return $this->userRoleService;
    }

    /**
     * Übersicht aller Fanfiction (inkl. Entwürfe) für Vorstand.
     */
    public function index(): View
    {
        $fanfictions = Fanfiction::with(['author', 'creator'])
            ->forTeam($this->memberTeam()->id)
            ->orderByDesc('created_at')
            ->get();

        return view('admin.fanfiction.index', [
            'fanfictions' => $fanfictions,
        ]);
    }

    /**
     * Formular zum Erstellen einer neuen Fanfiction.
     */
    public function create(): View
    {
        $members = User::whereHas('teams', function ($query) {
            $query->where('teams.id', $this->memberTeam()->id);
        })->orderBy('name')->get();

        return view('admin.fanfiction.create', [
            'members' => $members,
        ]);
    }

    /**
     * Speichert eine neue Fanfiction.
     */
    public function store(FanfictionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $photoPaths = $this->uploadPhotos($request);

        $fanfiction = Fanfiction::create([
            'team_id' => $this->memberTeam()->id,
            'user_id' => $validated['author_type'] === 'member' ? $validated['user_id'] : null,
            'created_by' => Auth::id(),
            'title' => $validated['title'],
            'author_name' => $validated['author_name'],
            'content' => $validated['content'],
            'photos' => $photoPaths ?: null,
            'status' => FanfictionStatus::from($validated['status']),
            'published_at' => $validated['status'] === 'published' ? now() : null,
        ]);

        if ($validated['status'] === 'published') {
            $this->fanfictionService->createRewardForFanfiction($fanfiction);
            $this->handlePublishActions($fanfiction);
            $this->invalidateFanfictionCountCache();
        }

        return redirect()
            ->route('admin.fanfiction.index')
            ->with('success', 'Fanfiction erfolgreich erstellt.');
    }

    /**
     * Formular zum Bearbeiten einer Fanfiction.
     */
    public function edit(Fanfiction $fanfiction): View
    {
        $members = User::whereHas('teams', function ($query) {
            $query->where('teams.id', $this->memberTeam()->id);
        })->orderBy('name')->get();

        return view('admin.fanfiction.edit', [
            'fanfiction' => $fanfiction,
            'members' => $members,
        ]);
    }

    /**
     * Aktualisiert eine vorhandene Fanfiction.
     */
    public function update(Request $request, Fanfiction $fanfiction): RedirectResponse
    {
        // Calculate available slots for new photos
        $existingCount = count($fanfiction->photos ?? []);
        $removeCount = count($request->input('remove_photos', []));
        $availableSlots = self::MAX_PHOTOS - $existingCount + $removeCount;

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author_type' => 'required|in:member,external',
            'user_id' => 'nullable|required_if:author_type,member|exists:users,id',
            'author_name' => 'required|string|max:255',
            'content' => 'required|string|min:10',
            'photos' => ['nullable', 'array', 'max:'.max(0, $availableSlots)],
            'photos.*' => 'file|max:'.self::MAX_PHOTO_SIZE_KB.'|mimes:'.implode(',', self::ALLOWED_PHOTO_EXTENSIONS),
            'remove_photos' => 'nullable|array',
            'remove_photos.*' => 'string',
        ]);

        // Handle photo removal
        $removePhotos = collect($request->input('remove_photos', []));
        $existingPhotos = collect($fanfiction->photos ?? []);
        $photosToKeep = $existingPhotos->reject(fn ($path) => $removePhotos->contains($path))->values();

        // Delete removed photos from storage
        $removedPhotos = $existingPhotos->diff($photosToKeep);
        foreach ($removedPhotos as $path) {
            Storage::disk('public')->delete($path);
        }

        // Upload new photos
        $newPhotoPaths = $this->uploadPhotos($request);

        // Merge existing and new photos (max 5)
        $allPhotos = $photosToKeep->merge($newPhotoPaths)->take(self::MAX_PHOTOS)->values()->toArray();

        $fanfiction->update([
            'user_id' => $validated['author_type'] === 'member' ? $validated['user_id'] : null,
            'title' => $validated['title'],
            'author_name' => $validated['author_name'],
            'content' => $validated['content'],
            'photos' => $allPhotos ?: null,
        ]);

        // Reward-Titel/Beschreibung synchronisieren (falls vorhanden)
        if ($fanfiction->reward) {
            $this->fanfictionService->updateRewardForFanfiction(
                $fanfiction,
                $fanfiction->reward->cost_baxx,
            );
        }

        return redirect()
            ->route('admin.fanfiction.index')
            ->with('success', 'Fanfiction erfolgreich aktualisiert.');
    }

    /**
     * Löscht eine Fanfiction.
     */
    public function destroy(Fanfiction $fanfiction): RedirectResponse
    {
        // Delete photos from storage
        foreach ($fanfiction->photos ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }

        $wasPublished = $fanfiction->status === FanfictionStatus::Published;
        $this->fanfictionService->deleteRewardForFanfiction($fanfiction);
        $fanfiction->delete();

        if ($wasPublished) {
            $this->invalidateFanfictionCountCache();
        }

        return redirect()
            ->route('admin.fanfiction.index')
            ->with('success', 'Fanfiction erfolgreich gelöscht.');
    }

    /**
     * Veröffentlicht einen Entwurf.
     */
    public function publish(Fanfiction $fanfiction): RedirectResponse
    {
        if ($fanfiction->status === FanfictionStatus::Published) {
            return redirect()
                ->route('admin.fanfiction.index')
                ->with('info', 'Diese Fanfiction ist bereits veröffentlicht.');
        }

        $fanfiction->update([
            'status' => FanfictionStatus::Published,
            'published_at' => now(),
        ]);

        // Reward erstellen beim Veröffentlichen
        if (! $fanfiction->reward) {
            $this->fanfictionService->createRewardForFanfiction($fanfiction);
        }

        $this->handlePublishActions($fanfiction);
        $this->invalidateFanfictionCountCache();

        return redirect()
            ->route('admin.fanfiction.index')
            ->with('success', 'Fanfiction erfolgreich veröffentlicht.');
    }

    /**
     * Upload photos and return array of paths.
     *
     * @return array<string>
     */
    private function uploadPhotos(Request $request): array
    {
        $photoPaths = [];

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                if (! $photo) {
                    continue;
                }

                try {
                    $extension = strtolower($photo->getClientOriginalExtension());
                    $name = Str::slug(pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME));
                    if ($name === '') {
                        $name = 'photo';
                    }
                    $filename = $name.'-'.Str::uuid().'.'.$extension;
                    $photoPaths[] = $photo->storeAs(self::PHOTO_STORAGE_PATH, $filename, 'public');
                } catch (\Throwable $e) {
                    // Rollback uploaded photos on failure
                    foreach ($photoPaths as $path) {
                        Storage::disk('public')->delete($path);
                    }

                    throw $e;
                }
            }
        }

        return $photoPaths;
    }

    /**
     * Handle actions when a fanfiction is published.
     */
    private function handlePublishActions(Fanfiction $fanfiction): void
    {
        // Create activity entry
        Activity::create([
            'user_id' => $fanfiction->user_id ?? $fanfiction->created_by,
            'subject_type' => Fanfiction::class,
            'subject_id' => $fanfiction->id,
            'action' => 'published',
        ]);

        // Award Baxx points if author is a member
        if ($fanfiction->user_id && $fanfiction->author) {
            $points = BaxxEarningRule::getPointsFor('fanfiction_publish');
            if ($points > 0) {
                $fanfiction->author->incrementTeamPoints($points);
            }
        }
    }

    /**
     * Invalidate the cached fanfiction count on the dashboard.
     */
    private function invalidateFanfictionCountCache(): void
    {
        $team = $this->memberTeam();
        Cache::forget("fanfiction_count_{$team->id}");
    }
}
