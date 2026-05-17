<x-app-layout title="Newsletter versenden">
    <x-member-page class="max-w-4xl">
        @php
            $initialTopics = \App\Support\NewsletterTopics::normalize(old('topics', []));

            if ($initialTopics === []) {
                $initialTopics = [\App\Support\NewsletterTopics::initialTopic()];
            }
        @endphp

        {{-- Flash Messages --}}
        @if(session('status'))
            <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
                {{ session('status') }}
            </x-alert>
        @endif

        <x-ui.page-header
            eyebrow="Adminbereich"
            title="Newsletter versenden"
            description="Plane Versand, wähle Zielgruppen nach Rollen aus und strukturiere Inhalte in mehrere Themenblöcke."
        />

        <x-ui.panel title="Newsletter-Inhalt" description="Betreff, Rollen und Themenblöcke werden gemeinsam vorbereitet, bevor der Versand bestätigt wird.">

            <form x-data='newsletterForm(@json($initialTopics))' x-ref="form" method="POST" action="{{ route('newsletter.send') }}" enctype="multipart/form-data">
                @csrf
                
                {{-- Betreff --}}
                <div class="mb-4">
                    <x-input label="Betreff" id="subject" name="subject" :value="old('subject')" required />
                </div>

                {{-- Zielgruppen --}}
                <div class="mb-4">
                    <label class="label">
                        <span class="label-text">Zielgruppen</span>
                    </label>
                    <div class="mt-2 space-y-2">
                        @foreach($roles as $role)
                            <x-checkbox name="roles[]" :value="$role->value" :checked="in_array($role->value, old('roles', [$defaultRole->value]), true)" :label="$role->value" />
                        @endforeach
                    </div>
                </div>

                @error('topics')
                    <p class="mb-4 text-sm text-error">{{ $message }}</p>
                @enderror

                {{-- Dynamische Themen --}}
                <template x-for="(topic, index) in topics" :key="topic.key">
                    <x-ui.panel class="mb-4 bg-base-200/70 shadow-none">
                        <div class="space-y-3">
                            <input x-bind:name="'topics[' + index + '][key]'" x-model="topic.key" type="hidden">
                            <div>
                                <label class="label" x-bind:for="'topic-title-' + index">
                                    <span class="label-text">Thema</span>
                                </label>
                                <input x-bind:id="'topic-title-' + index" type="text" class="input input-bordered w-full" x-bind:name="'topics[' + index + '][title]'" x-model="topic.title" required />
                            </div>
                            <div>
                                <label class="label" x-bind:for="'topic-content-' + index">
                                    <span class="label-text">Text</span>
                                </label>
                                <textarea x-bind:id="'topic-content-' + index" class="textarea textarea-bordered w-full" rows="6" x-bind:name="'topics[' + index + '][content]'" x-model="topic.content" required></textarea>
                                <p class="mt-2 text-xs text-base-content/60">Markdown wird unterstützt. Die Formatierung erscheint später im Archiv und in der Mail als HTML.</p>
                            </div>
                            <div>
                                <label class="label" x-bind:for="'topic-images-' + index">
                                    <span class="label-text">Bilder</span>
                                </label>
                                <input x-bind:id="'topic-images-' + index" type="file" x-bind:name="'topics[' + index + '][images][]'" accept="image/jpeg,image/png,image/gif,image/webp" multiple class="file-input file-input-bordered w-full">
                                <p class="mt-2 text-xs text-base-content/60">Mehrere Bilder pro Thema sind möglich. Unterstützt werden JPG, PNG, GIF und WEBP.</p>
                            </div>
                        </div>
                    </x-ui.panel>
                </template>

                {{-- Aktions-Buttons --}}
                <div class="flex flex-wrap gap-2">
                    <x-button label="Thema hinzufügen" icon="o-plus" class="btn-ghost" @click="addTopic" />
                    <x-button label="Versenden" icon="o-paper-airplane" class="btn-primary" @click="confirmSend()" />
                </div>

                {{-- Confirm-Modal --}}
                <div x-show="showConfirm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-base-300/75">
                    <x-ui.panel title="Bestätigung" class="mx-4 w-full max-w-md">
                        <p class="mb-6 text-base-content">Wirklich Newsletter versenden?</p>
                        <div class="flex justify-end gap-2">
                            <x-button label="Abbrechen" class="btn-ghost" @click="showConfirm = false" />
                            <x-button label="Ja, versenden" class="btn-primary" @click="submit" />
                        </div>
                    </x-ui.panel>
                </div>
            </form>
        </x-ui.panel>
    </x-member-page>
</x-app-layout>

<script>
function newsletterForm(initialTopics) {
    return {
        topics: Array.isArray(initialTopics) && initialTopics.length > 0
            ? initialTopics.map((topic) => ({
                key: topic.key || crypto.randomUUID(),
                title: topic.title || '',
                content: topic.content || '',
            }))
            : [{ key: crypto.randomUUID(), title: '', content: '' }],
        showConfirm: false,
        addTopic() {
            this.topics.push({ key: crypto.randomUUID(), title: '', content: '' });
        },
        confirmSend() {
            this.showConfirm = true;
        },
        submit() {
            this.$refs.form.submit();
        }
    }
}
</script>
