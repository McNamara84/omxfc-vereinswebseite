<x-app-layout title="Newsletter versenden">
    <x-member-page class="max-w-4xl">
        {{-- Flash Messages --}}
        @if(session('status'))
            <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
                {{ session('status') }}
            </x-alert>
        @endif

        <x-card shadow>
            <x-header title="Newsletter versenden" separator />

            <form x-data="newsletterForm()" x-ref="form" method="POST" action="{{ route('newsletter.send') }}">
                @csrf
                
                {{-- Betreff --}}
                <div class="mb-4">
                    <x-input label="Betreff" id="subject" name="subject" required />
                </div>

                {{-- Zielgruppen --}}
                <div class="mb-4">
                    <label class="label">
                        <span class="label-text">Zielgruppen</span>
                    </label>
                    <div class="mt-2 space-y-2">
                        @foreach($roles as $role)
                            <x-checkbox name="roles[]" value="{{ $role->value }}" :checked="$role === $defaultRole" label="{{ $role->value }}" />
                        @endforeach
                    </div>
                </div>

                {{-- Dynamische Themen --}}
                <template x-for="(topic, index) in topics" :key="index">
                    <x-card class="mb-4 bg-base-200">
                        <div class="space-y-3">
                            <div>
                                <label class="label" x-bind:for="'topic-title-' + index">
                                    <span class="label-text">Thema</span>
                                </label>
                                <input x-bind:id="'topic-title-' + index" type="text" class="input input-bordered w-full" x-bind:name="'topics[' + index + '][title]'" required />
                            </div>
                            <div>
                                <label class="label" x-bind:for="'topic-content-' + index">
                                    <span class="label-text">Text</span>
                                </label>
                                <textarea x-bind:id="'topic-content-' + index" class="textarea textarea-bordered w-full" rows="3" x-bind:name="'topics[' + index + '][content]'" required></textarea>
                            </div>
                        </div>
                    </x-card>
                </template>

                {{-- Aktions-Buttons --}}
                <div class="flex flex-wrap gap-2">
                    <x-button label="Thema hinzufügen" icon="o-plus" class="btn-ghost" @click="addTopic" />
                    <x-button label="Versenden" icon="o-paper-airplane" class="btn-primary" @click="confirmSend()" />
                    <x-button label="Newsletter testen" icon="o-beaker" class="btn-secondary" @click="confirmSend(true)" />
                </div>
                
                <input type="hidden" name="test" value="0" x-ref="test" />

                {{-- Confirm-Modal --}}
                <div x-show="showConfirm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-base-300/75">
                    <x-card class="max-w-md w-full mx-4 shadow-xl">
                        <x-header title="Bestätigung" class="!mb-2" />
                        <p class="mb-6 text-base-content" x-text="pendingTest ? 'Testnewsletter wirklich versenden?' : 'Wirklich Newsletter versenden?'"></p>
                        <div class="flex justify-end gap-2">
                            <x-button label="Abbrechen" class="btn-ghost" @click="showConfirm = false" />
                            <x-button label="Ja, versenden" class="btn-primary" @click="submit" />
                        </div>
                    </x-card>
                </div>
            </form>
        </x-card>
    </x-member-page>
</x-app-layout>

<script>
function newsletterForm() {
    return {
        topics: [{}],
        showConfirm: false,
        pendingTest: false,
        addTopic() { this.topics.push({}); },
        confirmSend(isTest = false) {
            this.pendingTest = isTest;
            this.showConfirm = true;
        },
        submit() {
            if (this.pendingTest) {
                this.$refs.test.value = 1;
            }
            this.$refs.form.submit();
        }
    }
}
</script>
