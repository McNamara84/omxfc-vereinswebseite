<x-app-layout title="Newsletter-Archiv bearbeiten">
    <x-member-page class="max-w-5xl space-y-8">
        @php
            $initialTopics = \App\Support\NewsletterTopics::normalize(old('topics', $newsletterAusgabe->topics ?? []));

            if ($initialTopics === []) {
                $initialTopics = [\App\Support\NewsletterTopics::initialTopic()];
            }
        @endphp

        @if (session('status'))
            <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
                {{ session('status') }}
            </x-alert>
        @endif

        <x-ui.page-header
            eyebrow="Adminbereich"
            title="Newsletter-Ausgabe bearbeiten"
            description="Pflege Betreff, Slug, Versandzeit und Themenblöcke des Archiv-Eintrags."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    @if ($newsletterAusgabe->status === \App\Enums\NewsletterAusgabeStatus::Veroeffentlicht)
                        <x-badge value="Veröffentlicht" class="badge-success" icon="o-check-circle" />
                        <x-button label="Im Archiv ansehen" link="{{ route('newsletter.archiv.show', $newsletterAusgabe) }}" class="btn-ghost btn-sm" icon="o-arrow-top-right-on-square" />
                    @else
                        <x-badge value="Entwurf" class="badge-warning" icon="o-pencil-square" />
                    @endif
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.panel title="Stammdaten" description="Der Eintrag kann redaktionell angepasst werden, bevor er für Mitglieder freigeschaltet wird.">
            <form
                method="POST"
                action="{{ route('newsletter.archiv.admin.update', $newsletterAusgabe) }}"
                x-data='newsletterArchivForm(@json($initialTopics))'
                class="space-y-6"
                enctype="multipart/form-data"
            >
                @csrf
                @method('PUT')

                <div class="grid gap-4 md:grid-cols-2">
                    <x-input name="subject" label="Betreff" :value="old('subject', $newsletterAusgabe->subject)" required />
                    <x-input name="slug" label="Slug" :value="old('slug', $newsletterAusgabe->slug)" required />
                    <x-input
                        name="sent_at"
                        type="datetime-local"
                        label="Versendet am"
                        :value="old('sent_at', optional($newsletterAusgabe->sent_at)->format('Y-m-d\TH:i'))"
                    />
                </div>

                <div>
                    <label class="label">
                        <span class="label-text">Zielgruppen</span>
                    </label>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($roles as $role)
                            <x-checkbox
                                name="recipient_roles[]"
                                :value="$role->value"
                                :checked="in_array($role->value, old('recipient_roles', $newsletterAusgabe->recipient_roles ?? []), true)"
                                :label="$role->value"
                            />
                        @endforeach
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">Themenblöcke</h2>
                            <p class="text-sm text-base-content/70">Die Reihenfolge hier entspricht der Darstellung im Archiv und in der Mailansicht.</p>
                        </div>
                        <x-button type="button" label="Thema hinzufügen" class="btn-ghost btn-sm" icon="o-plus" @click="addTopic" />
                    </div>

                    @error('topics')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror

                    <template x-for="(topic, index) in topics" :key="topic.key">
                        <x-ui.panel class="bg-base-200/70 shadow-none">
                            <div class="space-y-3">
                                <input x-bind:name="'topics[' + index + '][key]'" x-model="topic.key" type="hidden">
                                <div class="flex justify-end" x-show="topics.length > 1">
                                    <x-button type="button" label="Entfernen" class="btn-ghost btn-xs" icon="o-trash" @click="removeTopic(index)" />
                                </div>
                                <div>
                                    <label class="label" x-bind:for="'topic-title-' + index">
                                        <span class="label-text">Thema</span>
                                    </label>
                                    <input x-bind:id="'topic-title-' + index" x-bind:name="'topics[' + index + '][title]'" x-model="topic.title" type="text" class="input input-bordered w-full" required>
                                </div>
                                <div>
                                    <label class="label" x-bind:for="'topic-content-' + index">
                                        <span class="label-text">Text</span>
                                    </label>
                                    <textarea x-bind:id="'topic-content-' + index" x-bind:name="'topics[' + index + '][content]'" x-model="topic.content" class="textarea textarea-bordered w-full" rows="6" required></textarea>
                                    <p class="mt-2 text-xs text-base-content/60">Markdown wird unterstützt. Die Formatierung erscheint später im Archiv und in der Mail als HTML.</p>
                                </div>

                                <div class="space-y-3" x-show="topic.images.length > 0">
                                    <p class="text-sm font-medium text-base-content">Bereits gespeicherte Bilder</p>

                                    <template x-for="image in topic.images" :key="image">
                                        <div class="rounded-2xl border border-base-content/10 bg-base-100/70 p-3">
                                            <div class="grid gap-3 md:grid-cols-[10rem_1fr] md:items-center">
                                                <img :src="'/storage/' + image" :alt="'Bild zu ' + (topic.title || 'Ohne Titel')" class="h-32 w-full rounded-xl object-cover">
                                                <label class="label cursor-pointer justify-start gap-3">
                                                    <input type="checkbox" class="checkbox checkbox-sm" :name="'topics[' + index + '][remove_images][]'" :value="image">
                                                    <span class="label-text">Bild beim Speichern entfernen</span>
                                                </label>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div>
                                    <label class="label" x-bind:for="'topic-images-' + index">
                                        <span class="label-text">Neue Bilder</span>
                                    </label>
                                    <input x-bind:id="'topic-images-' + index" x-bind:name="'topics[' + index + '][images][]'" type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple class="file-input file-input-bordered w-full">
                                    <p class="mt-2 text-xs text-base-content/60">Mehrere Bilder pro Thema sind möglich. Neue Uploads werden an die vorhandenen Bilder angehängt.</p>
                                </div>
                            </div>
                        </x-ui.panel>
                    </template>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-button type="submit" label="Änderungen speichern" class="btn-primary" icon="o-check" />
                    <x-button type="button" label="Zur Übersicht" link="{{ route('newsletter.archiv.admin.index') }}" class="btn-ghost" icon="o-arrow-left" />
                </div>
            </form>

            @if ($newsletterAusgabe->status !== \App\Enums\NewsletterAusgabeStatus::Veroeffentlicht)
                <form method="POST" action="{{ route('newsletter.archiv.admin.publish', $newsletterAusgabe) }}" class="mt-4">
                    @csrf
                    <x-button type="submit" label="Jetzt veröffentlichen" class="btn-secondary" icon="o-paper-airplane" />
                </form>
            @endif
        </x-ui.panel>
    </x-member-page>

    <script>
        function newsletterArchivForm(initialTopics) {
            const topics = Array.isArray(initialTopics) && initialTopics.length > 0
                ? initialTopics.map((topic) => ({
                    key: topic.key || crypto.randomUUID(),
                    title: topic.title ?? '',
                    content: topic.content ?? '',
                    images: Array.isArray(topic.images) ? topic.images : [],
                }))
                : [{ key: crypto.randomUUID(), title: '', content: '', images: [] }];

            return {
                topics,
                addTopic() {
                    this.topics.push({ key: crypto.randomUUID(), title: '', content: '', images: [] });
                },
                removeTopic(index) {
                    if (this.topics.length === 1) {
                        return;
                    }

                    this.topics.splice(index, 1);
                },
            };
        }
    </script>
</x-app-layout>