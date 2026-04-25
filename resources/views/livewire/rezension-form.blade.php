<x-member-page class="max-w-3xl">
    <x-header separator>
        <x-slot:title>{{ $formTitle }}</x-slot:title>
    </x-header>

    @unless($isEdit)
        <x-alert title="Hinweis" description="Du kannst die Rezensionen zu diesem Roman erst lesen, nachdem du selbst eine verfasst und gespeichert hast." icon="o-exclamation-triangle" class="alert-warning mb-6" />
    @endunless

    <x-card>
        <form wire:submit="save">
            <div class="space-y-4">
                <x-input
                    wire:model="title"
                    label="Rezensionstitel"
                    required
                />
                @error('title') <p class="text-error text-sm">{{ $message }}</p> @enderror

                <x-textarea
                    wire:model="content"
                    label="Rezensionstext"
                    rows="8"
                    hint="Mindestens 140 Zeichen."
                    required
                />
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
    </x-card>
</x-member-page>
