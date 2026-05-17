<x-app-layout title="Newsletter-Archiv bearbeiten">
    <x-member-page class="max-w-5xl space-y-8">
        @if (session('status'))
            <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
                {{ session('status') }}
            </x-alert>
        @endif

        <x-ui.page-header
            eyebrow="Adminbereich"
            title="Newsletter-Ausgabe bearbeiten"
            description="Pflege Betreff, Slug, Versandzeit und Themenbloecke des Archiv-Eintrags."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    @if ($newsletterAusgabe->status === \App\Enums\NewsletterAusgabeStatus::Veroeffentlicht)
                        <x-badge value="Veroeffentlicht" class="badge-success" icon="o-check-circle" />
                        <x-button label="Im Archiv ansehen" link="{{ route('newsletter.archiv.show', $newsletterAusgabe) }}" class="btn-ghost btn-sm" icon="o-arrow-top-right-on-square" />
                    @else
                        <x-badge value="Entwurf" class="badge-warning" icon="o-pencil-square" />
                    @endif
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.panel title="Stammdaten" description="Der Eintrag kann redaktionell angepasst werden, bevor er fuer Mitglieder freigeschaltet wird.">
            <form
                method="POST"
                action="{{ route('newsletter.archiv.admin.update', $newsletterAusgabe) }}"
                x-data="newsletterArchivForm({ topics: @js(old('topics', $newsletterAusgabe->topics ?? [])) })"
                class="space-y-6"
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
                        required
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
                            <h2 class="text-lg font-semibold">Themenbloecke</h2>
                            <p class="text-sm text-base-content/70">Die Reihenfolge hier entspricht der Darstellung im Archiv und in der Mailansicht.</p>
                        </div>
                        <x-button type="button" label="Thema hinzufuegen" class="btn-ghost btn-sm" icon="o-plus" @click="addTopic" />
                    </div>

                    <template x-for="(topic, index) in topics" :key="index">
                        <x-ui.panel class="bg-base-200/70 shadow-none">
                            <div class="space-y-3">
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
                                </div>
                            </div>
                        </x-ui.panel>
                    </template>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-button type="submit" label="Aenderungen speichern" class="btn-primary" icon="o-check" />
                    <x-button type="button" label="Zur Uebersicht" link="{{ route('newsletter.archiv.admin.index') }}" class="btn-ghost" icon="o-arrow-left" />
                </div>
            </form>

            @if ($newsletterAusgabe->status !== \App\Enums\NewsletterAusgabeStatus::Veroeffentlicht)
                <form method="POST" action="{{ route('newsletter.archiv.admin.publish', $newsletterAusgabe) }}" class="mt-4">
                    @csrf
                    <x-button type="submit" label="Jetzt veroeffentlichen" class="btn-secondary" icon="o-paper-airplane" />
                </form>
            @endif
        </x-ui.panel>
    </x-member-page>

    <script>
        function newsletterArchivForm({ topics }) {
            const initialTopics = Array.isArray(topics) && topics.length > 0
                ? topics.map((topic) => ({
                    title: topic.title ?? '',
                    content: topic.content ?? '',
                }))
                : [{ title: '', content: '' }];

            return {
                topics: initialTopics,
                addTopic() {
                    this.topics.push({ title: '', content: '' });
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