<?php

namespace App\Http\Controllers;

use App\Enums\NewsletterAusgabeStatus;
use App\Models\NewsletterAusgabe;
use App\Services\NewsletterImageService;
use App\Support\NewsletterTopics;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NewsletterArchivAdminController extends Controller
{
    public function __construct(
        private readonly NewsletterImageService $newsletterImageService,
    ) {}

    public function index(): View
    {
        $newsletterAusgaben = NewsletterAusgabe::query()
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('newsletter.archiv.admin.index', [
            'newsletterAusgaben' => $newsletterAusgaben,
        ]);
    }

    public function store(): RedirectResponse
    {
        $newsletterAusgabe = NewsletterAusgabe::query()->create([
            'subject' => 'Neue Archiv-Ausgabe',
            'topics' => [],
            'recipient_roles' => [NewsletterAusgabe::defaultRecipientRole()->value],
            'status' => NewsletterAusgabeStatus::Entwurf,
            'sent_at' => null,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('newsletter.archiv.admin.edit', $newsletterAusgabe)
            ->with('status', 'Archiv-Entwurf angelegt.');
    }

    public function edit(NewsletterAusgabe $newsletterAusgabe): View
    {
        return view('newsletter.archiv.admin.edit', [
            'newsletterAusgabe' => $newsletterAusgabe,
            'roles' => NewsletterAusgabe::recipientRoles(),
        ]);
    }

    public function update(Request $request, NewsletterAusgabe $newsletterAusgabe): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'recipient_roles' => ['required', 'array', 'min:1'],
            'recipient_roles.*' => ['string', Rule::in(NewsletterAusgabe::recipientRoleValues())],
            'sent_at' => ['nullable', 'date'],
            'topics' => ['required', 'array', 'min:1'],
            'topics.*.key' => ['nullable', 'string', 'max:255'],
            'topics.*.title' => ['required', 'string', 'max:255'],
            'topics.*.content' => ['required', 'string'],
            'topics.*.remove_images' => ['nullable', 'array'],
            'topics.*.remove_images.*' => ['string'],
            'topics.*.images' => ['nullable', 'array'],
            'topics.*.images.*' => ['image', 'mimes:jpg,jpeg,png,gif,webp', 'max:'.NewsletterImageService::MAX_FILE_SIZE_KB],
        ]);

        try {
            $topics = $this->prepareTopicsForUpdate($newsletterAusgabe, $validated['topics']);
        } catch (\RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors(['topics' => $exception->getMessage()]);
        }

        $newsletterAusgabe->update([
            'subject' => $validated['subject'],
            'slug' => NewsletterAusgabe::generateUniqueSlug(
                $validated['slug'],
                $validated['subject'],
                $newsletterAusgabe,
            ),
            'recipient_roles' => $validated['recipient_roles'],
            'sent_at' => filled($validated['sent_at'] ?? null)
                ? Carbon::parse($validated['sent_at'])
                : null,
            'topics' => $topics['topics'],
        ]);

        $this->newsletterImageService->deleteImages($topics['delete_after_save']);

        return redirect()
            ->route('newsletter.archiv.admin.edit', $newsletterAusgabe->fresh())
            ->with('status', 'Archiv-Eintrag aktualisiert.');
    }

    public function publish(NewsletterAusgabe $newsletterAusgabe): RedirectResponse
    {
        $newsletterAusgabe->update([
            'status' => NewsletterAusgabeStatus::Veroeffentlicht,
            'published_at' => $newsletterAusgabe->published_at ?? now(),
        ]);

        return redirect()
            ->route('newsletter.archiv.admin.edit', $newsletterAusgabe)
            ->with('status', 'Newsletter-Ausgabe veröffentlicht.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $topics
     * @return array{topics: array<int, array{key: string, title: string, content: string, images: array<int, string>}>, delete_after_save: array<int, string>}
     */
    private function prepareTopicsForUpdate(NewsletterAusgabe $newsletterAusgabe, array $topics): array
    {
        $currentTopics = NewsletterTopics::normalize($newsletterAusgabe->topics ?? []);
        $currentTopicsByKey = collect($currentTopics)->keyBy('key');
        $submittedTopics = NewsletterTopics::normalize($topics);
        $preparedTopics = [];
        $uploadedImages = [];
        $deleteAfterSave = [];

        try {
            foreach ($submittedTopics as $index => $topic) {
                $currentTopic = $currentTopicsByKey->get($topic['key']);
                $existingImages = $currentTopic['images'] ?? [];
                $removedImages = array_values(array_intersect(
                    $existingImages,
                    array_values(array_filter(array_map(
                        static fn (mixed $path): string => is_string($path) ? trim($path) : '',
                        $topics[$index]['remove_images'] ?? [],
                    ))),
                ));
                $sync = $this->newsletterImageService->syncImages(
                    $existingImages,
                    $removedImages,
                    $topics[$index]['images'] ?? [],
                );

                $uploadedImages = array_merge($uploadedImages, $sync['uploaded']);
                $deleteAfterSave = array_merge($deleteAfterSave, $sync['deleted']);
                $preparedTopics[] = [
                    'key' => $currentTopic && ! NewsletterTopics::usesLegacyKey($topic['key'])
                        ? $topic['key']
                        : NewsletterTopics::generatePersistentKey(),
                    'title' => $topic['title'],
                    'content' => $topic['content'],
                    'images' => $sync['images'],
                ];
            }
        } catch (\RuntimeException $exception) {
            $this->newsletterImageService->deleteImages($uploadedImages);

            throw $exception;
        }

        $submittedKeys = array_map(
            static fn (array $topic): string => $topic['key'],
            $submittedTopics,
        );

        foreach ($currentTopics as $currentTopic) {
            if (! in_array($currentTopic['key'], $submittedKeys, true)) {
                $deleteAfterSave = array_merge($deleteAfterSave, $currentTopic['images']);
            }
        }

        return [
            'topics' => $preparedTopics,
            'delete_after_save' => array_values(array_unique($deleteAfterSave)),
        ];
    }
}