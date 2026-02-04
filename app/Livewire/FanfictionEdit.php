<?php

namespace App\Livewire;

use App\Enums\FanfictionStatus;
use App\Models\Fanfiction;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class FanfictionEdit extends Component
{
    use WithFileUploads;

    public const ALLOWED_PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const MAX_PHOTOS = 5;

    public const MAX_PHOTO_SIZE_KB = 2048;

    public const PHOTO_STORAGE_PATH = 'fanfiction';

    #[Locked]
    public int $fanfictionId;

    public string $title = '';

    public string $authorType = 'member';

    public ?int $userId = null;

    public string $authorName = '';

    public string $content = '';

    public array $existingPhotos = [];

    public array $photosToRemove = [];

    public array $newPhotos = [];

    public function mount(Fanfiction $fanfiction): void
    {
        $this->fanfictionId = $fanfiction->id;
        $this->title = $fanfiction->title;
        $this->authorType = $fanfiction->user_id ? 'member' : 'external';
        $this->userId = $fanfiction->user_id;
        $this->authorName = $fanfiction->author_name;
        $this->content = $fanfiction->content;
        $this->existingPhotos = $fanfiction->photos ?? [];
    }

    #[Computed]
    public function fanfiction(): Fanfiction
    {
        return Fanfiction::findOrFail($this->fanfictionId);
    }

    #[Computed]
    public function members()
    {
        $membersTeam = Team::membersTeam();

        return User::whereHas('teams', function ($query) use ($membersTeam) {
            $query->where('teams.id', $membersTeam->id);
        })->orderBy('name')->get();
    }

    #[Computed]
    public function memberOptions(): array
    {
        return $this->members->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
        ])->toArray();
    }

    public function updatedUserId($value): void
    {
        if ($value && $this->authorType === 'member') {
            $member = $this->members->firstWhere('id', $value);
            if ($member) {
                $this->authorName = $member->name;
            }
        }
    }

    public function updatedAuthorType($value): void
    {
        if ($value === 'external') {
            $this->userId = null;
            $this->authorName = '';
        }
    }

    public function togglePhotoRemoval(string $photo): void
    {
        if (in_array($photo, $this->photosToRemove)) {
            $this->photosToRemove = array_values(array_diff($this->photosToRemove, [$photo]));
        } else {
            $this->photosToRemove[] = $photo;
        }
    }

    public function save(): void
    {
        $existingCount = count($this->existingPhotos);
        $removeCount = count($this->photosToRemove);
        $availableSlots = self::MAX_PHOTOS - $existingCount + $removeCount;

        $this->validate([
            'title' => 'required|string|max:255',
            'authorType' => 'required|in:member,external',
            'userId' => $this->authorType === 'member' ? 'required|exists:users,id' : 'nullable',
            'authorName' => 'required|string|max:255',
            'content' => 'required|string|min:10',
            'newPhotos' => 'nullable|array|max:'.max(0, $availableSlots),
            'newPhotos.*' => 'image|max:'.self::MAX_PHOTO_SIZE_KB,
        ]);

        $fanfiction = $this->fanfiction;

        // Handle photo removal
        $photosToKeep = collect($this->existingPhotos)
            ->reject(fn ($path) => in_array($path, $this->photosToRemove))
            ->values();

        // Delete removed photos from storage
        foreach ($this->photosToRemove as $path) {
            Storage::disk('public')->delete($path);
        }

        // Upload new photos
        $newPhotoPaths = $this->uploadPhotos();

        // Merge existing and new photos (max 5)
        $allPhotos = $photosToKeep->merge($newPhotoPaths)->take(self::MAX_PHOTOS)->values()->toArray();

        $fanfiction->update([
            'user_id' => $this->authorType === 'member' ? $this->userId : null,
            'title' => $this->title,
            'author_name' => $this->authorName,
            'content' => $this->content,
            'photos' => $allPhotos ?: null,
        ]);

        session()->flash('success', 'Fanfiction erfolgreich aktualisiert.');
        $this->redirect(route('admin.fanfiction.index'));
    }

    private function uploadPhotos(): array
    {
        $photoPaths = [];

        foreach ($this->newPhotos as $photo) {
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
                foreach ($photoPaths as $path) {
                    Storage::disk('public')->delete($path);
                }

                throw $e;
            }
        }

        return $photoPaths;
    }

    public function render()
    {
        return view('livewire.fanfiction-edit')
            ->layout('layouts.app', ['title' => 'Fanfiction bearbeiten']);
    }
}
