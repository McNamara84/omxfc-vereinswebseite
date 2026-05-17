<?php

namespace App\Http\Controllers;

use App\Enums\NewsletterAusgabeStatus;
use App\Enums\Role;
use App\Mail\Newsletter;
use App\Models\NewsletterAusgabe;
use App\Models\Team;
use App\Services\NewsletterImageService;
use App\Support\NewsletterTopics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class NewsletterController extends Controller
{
    public function __construct(
        private readonly NewsletterImageService $newsletterImageService,
    ) {}

    /**
     * Display the newsletter form.
     */
    public function create()
    {
        $this->abortUnlessCanManageNewsletter();

        $roles = NewsletterAusgabe::recipientRoles();
        $defaultRole = NewsletterAusgabe::defaultRecipientRole();

        return view('newsletter.versenden', compact('roles', 'defaultRole'));
    }

    /**
     * Send the newsletter to the selected roles.
     */
    public function send(Request $request)
    {
        $user = Auth::user();
        $this->abortUnlessCanManageNewsletter();

        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(NewsletterAusgabe::recipientRoleValues())],
            'subject' => ['required', 'string', 'max:255'],
            'topics' => ['required', 'array', 'min:1'],
            'topics.*.key' => ['required', 'string', 'max:255', 'distinct'],
            'topics.*.title' => ['required', 'string', 'max:255'],
            'topics.*.content' => ['required', 'string'],
            'topics.*.images' => ['nullable', 'array'],
            'topics.*.images.*' => ['image', 'mimes:jpg,jpeg,png,gif,webp', 'max:'.NewsletterImageService::MAX_FILE_SIZE_KB],
        ]);

        $membersTeam = Team::membersTeam();
        if (! $membersTeam) {
            return back()->with('status', 'Team nicht gefunden.');
        }

        $recipients = $membersTeam->users()->wherePivotIn('role', $data['roles'])->get();

        if ($recipients->isEmpty()) {
            return redirect()->route('newsletter.create')
                ->with('status', 'Keine Empfänger für die ausgewählten Rollen gefunden.');
        }

        try {
            $topics = $this->prepareTopicsForStorage($data['topics']);
        } catch (\RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors(['topics' => $exception->getMessage()]);
        }

        NewsletterAusgabe::query()->create([
            'subject' => $data['subject'],
            'topics' => $topics,
            'recipient_roles' => $data['roles'],
            'status' => NewsletterAusgabeStatus::Entwurf,
            'sent_at' => now(),
            'created_by' => $user?->id,
        ]);

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->queue(new Newsletter($data['subject'], $topics));
        }

        return redirect()->route('newsletter.create')->with('status', 'Newsletter versendet.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $topics
     * @return array<int, array{key: string, title: string, content: string, images: array<int, string>}>
     */
    private function prepareTopicsForStorage(array $topics): array
    {
        $normalizedTopics = NewsletterTopics::ensureDistinctPersistentKeys(NewsletterTopics::normalize($topics));
        $preparedTopics = [];
        $uploadedImages = [];

        try {
            foreach ($normalizedTopics as $index => $topic) {
                $newImages = $this->newsletterImageService->uploadImages($topics[$index]['images'] ?? []);
                $uploadedImages = array_merge($uploadedImages, $newImages);

                $preparedTopics[] = [
                    'key' => $topic['key'],
                    'title' => $topic['title'],
                    'content' => $topic['content'],
                    'images' => $newImages,
                ];
            }
        } catch (\RuntimeException $exception) {
            $this->newsletterImageService->deleteImages($uploadedImages);

            throw new \RuntimeException('Mindestens ein Bild konnte nicht hochgeladen werden.', 0, $exception);
        }

        return $preparedTopics;
    }

    private function abortUnlessCanManageNewsletter(): void
    {
        $user = Auth::user();
        $team = $user?->currentTeam;

        if (! $team) {
            abort(403);
        }

        if (
            ! $team->hasUserWithRole($user, Role::Admin->value)
            && ! $team->hasUserWithRole($user, Role::Vorstand->value)
        ) {
            abort(403);
        }
    }
}
