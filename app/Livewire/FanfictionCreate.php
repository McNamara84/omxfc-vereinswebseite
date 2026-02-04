<?php

namespace App\Livewire;

use App\Enums\FanfictionStatus;
use App\Models\Activity;
use App\Models\Fanfiction;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class FanfictionCreate extends Component
{
    use WithFileUploads;

    public const ALLOWED_PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const MAX_PHOTOS = 5;

    public const MAX_PHOTO_SIZE_KB = 2048;

    public const PHOTO_STORAGE_PATH = 'fanfiction';

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('required|in:member,external')]
    public string $authorType = 'member';

    #[Validate('nullable|exists:users,id')]
    public ?int $userId = null;

    #[Validate('required|string|max:255')]
    public string $authorName = '';

    #[Validate('required|string|min:10')]
    public string $content = '';

    #[Validate('required|in:draft,published')]
    public string $status = 'draft';

    public array $photos = [];

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

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'authorType' => 'required|in:member,external',
            'userId' => $this->authorType === 'member' ? 'required|exists:users,id' : 'nullable',
            'authorName' => 'required|string|max:255',
            'content' => 'required|string|min:10',
            'status' => 'required|in:draft,published',
            'photos' => 'nullable|array|max:'.self::MAX_PHOTOS,
            'photos.*' => 'image|max:'.self::MAX_PHOTO_SIZE_KB,
        ]);

        $membersTeam = Team::membersTeam();
        $photoPaths = $this->uploadPhotos();

        $fanfiction = Fanfiction::create([
            'team_id' => $membersTeam->id,
            'user_id' => $this->authorType === 'member' ? $this->userId : null,
            'created_by' => Auth::id(),
            'title' => $this->title,
            'author_name' => $this->authorName,
            'content' => $this->content,
            'photos' => $photoPaths ?: null,
            'status' => FanfictionStatus::from($this->status),
            'published_at' => $this->status === 'published' ? now() : null,
        ]);

        if ($this->status === 'published') {
            $this->handlePublishActions($fanfiction);
            $this->invalidateFanfictionCountCache($membersTeam);
        }

        session()->flash('success', 'Fanfiction erfolgreich erstellt.');
        $this->redirect(route('admin.fanfiction.index'));
    }

    private function uploadPhotos(): array
    {
        $photoPaths = [];

        foreach ($this->photos as $photo) {
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

    private function handlePublishActions(Fanfiction $fanfiction): void
    {
        Activity::create([
            'user_id' => $fanfiction->user_id ?? $fanfiction->created_by,
            'subject_type' => Fanfiction::class,
            'subject_id' => $fanfiction->id,
            'action' => 'published',
        ]);

        if ($fanfiction->user_id && $fanfiction->author) {
            $fanfiction->author->incrementTeamPoints(5);
        }
    }

    private function invalidateFanfictionCountCache(Team $team): void
    {
        Cache::forget("fanfiction_count_{$team->id}");
    }

    public function render()
    {
        return view('livewire.fanfiction-create')
            ->layout('layouts.app', ['title' => 'Neue Fanfiction erstellen']);
    }
}
