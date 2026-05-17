<?php

namespace App\Http\Controllers;

use App\Enums\NewsletterAusgabeStatus;
use App\Models\NewsletterAusgabe;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NewsletterArchivAdminController extends Controller
{
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
            'topics.*.title' => ['required', 'string', 'max:255'],
            'topics.*.content' => ['required', 'string'],
        ]);

        $newsletterAusgabe->update([
            'subject' => $validated['subject'],
            'slug' => $this->uniqueSlug($validated['slug'], $validated['subject'], $newsletterAusgabe),
            'recipient_roles' => $validated['recipient_roles'],
            'sent_at' => filled($validated['sent_at'] ?? null)
                ? Carbon::parse($validated['sent_at'])
                : null,
            'topics' => $validated['topics'],
        ]);

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

    private function uniqueSlug(string $slugInput, string $subject, ?NewsletterAusgabe $ignore = null): string
    {
        $baseSlug = Str::slug($slugInput) ?: Str::slug($subject) ?: 'newsletter';
        $slug = $baseSlug;
        $counter = 2;

        while (NewsletterAusgabe::query()
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}