<x-member-page class="max-w-3xl">
    <x-ui.page-header
        :title="$formTitle"
        :description="$isEdit
            ? 'Überarbeite deine bestehende Rezension und veröffentliche Änderungen direkt im Kontext des Romans.'
            : 'Verfasse deine erste Rezension zu diesem Roman und schalte danach die bestehenden Stimmen der anderen Mitglieder frei.'"
    />

    @unless($isEdit)
        <x-alert title="Hinweis" description="Du kannst die Rezensionen zu diesem Roman erst lesen, nachdem du selbst eine verfasst und gespeichert hast." icon="o-exclamation-triangle" class="alert-warning mb-6" />
    @endunless

    <x-ui.panel title="Deine Rezension" description="Titel und Text werden direkt validiert. Der Rezensionstext benötigt mindestens 140 Zeichen.">
        <form wire:submit="save">
            <div class="space-y-4">
                <x-input
                    wire:model="title"
                    label="Rezensionstitel"
                    required
                />
                @error('title') <p class="text-error text-sm">{{ $message }}</p> @enderror

                <div class="space-y-3" data-markdown-editor data-testid="review-markdown-editor">
                    <div class="space-y-2">
                        <div
                            class="flex flex-wrap gap-2"
                            role="toolbar"
                            aria-label="Markdown-Werkzeuge für den Rezensionstext"
                            data-testid="review-markdown-toolbar"
                        >
                            <button type="button" class="btn btn-sm btn-outline" data-markdown-action="bold" aria-controls="review-content-input" data-testid="review-markdown-bold">Fett</button>
                            <button type="button" class="btn btn-sm btn-outline" data-markdown-action="italic" aria-controls="review-content-input" data-testid="review-markdown-italic">Kursiv</button>
                            <button type="button" class="btn btn-sm btn-outline" data-markdown-action="bullet-list" aria-controls="review-content-input" data-testid="review-markdown-bullet-list">Bullet-Liste</button>
                            <button type="button" class="btn btn-sm btn-outline" data-markdown-action="numbered-list" aria-controls="review-content-input" data-testid="review-markdown-numbered-list">Nummerierte Liste</button>
                            <button type="button" class="btn btn-sm btn-outline" data-markdown-action="link" aria-controls="review-content-input" data-testid="review-markdown-link">Link</button>
                        </div>

                        <p id="review-markdown-help" class="text-xs text-base-content/60">
                            Unterstuetzt werden Fett, Kursiv, Bullet-Listen, nummerierte Listen und Hyperlinks. Keine Vorschau, keine weiteren Markdown-Werkzeuge.
                        </p>
                    </div>

                    <x-textarea
                        id="review-content-input"
                        wire:model="content"
                        label="Rezensionstext"
                        rows="10"
                        hint="Mindestens 140 Zeichen."
                        aria-describedby="review-markdown-help"
                        data-markdown-input
                        data-testid="review-markdown-input"
                        required
                    />
                </div>
                @error('content') <p class="text-error text-sm">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <x-button
                    label="Abbrechen"
                    link="{{ $isEdit ? route('reviews.show', $book) : route('reviews.index') }}"
                    wire:navigate
                    class="btn-ghost"
                />
                <x-button
                    label="{{ $isEdit ? 'Rezension aktualisieren' : 'Rezension absenden' }}"
                    type="submit"
                    class="btn-primary"
                    icon="o-check"
                    wire:loading.attr="disabled"
                />
            </div>
        </form>
    </x-ui.panel>
</x-member-page>
