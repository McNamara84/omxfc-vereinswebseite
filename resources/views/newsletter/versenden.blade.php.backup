<x-app-layout title="Newsletter versenden">
    <x-member-page class="max-w-4xl">
        @if(session('status'))
            <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-800 dark:text-green-200 rounded">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <h1 class="text-2xl font-bold text-[#8B0116] dark:text-[#FF6B81] mb-6">Newsletter versenden</h1>

            <form x-data="newsletterForm()" x-ref="form" method="POST" action="{{ route('newsletter.send') }}">
                @csrf
                <div class="mb-4">
                    <x-label for="subject" value="Betreff" />
                    <x-input id="subject" name="subject" type="text" class="mt-1 block w-full" required />
                </div>
                <div class="mb-4">
                    <x-label value="Zielgruppen" />
                    <div class="mt-2 space-y-2">
                        @foreach($roles as $role)
                            <label class="flex items-center text-gray-700 dark:text-gray-200">
                                {{-- Mitglied is the typical target audience and therefore pre-selected --}}
                                <x-checkbox name="roles[]" value="{{ $role->value }}" @checked($role === $defaultRole) />
                                <span class="ml-2">{{ $role->value }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <template x-for="(topic, index) in topics" :key="index">
                    <div class="mb-4">
                        <x-label x-bind:for="'topic-title-' + index" value="Thema" />
                        <x-input x-bind:id="'topic-title-' + index" type="text" class="mt-1 block w-full" x-bind:name="'topics[' + index + '][title]'" required />
                        <x-label x-bind:for="'topic-content-' + index" value="Text" class="mt-2" />
                        <textarea x-bind:id="'topic-content-' + index" class="mt-1 block w-full rounded bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200" rows="3" x-bind:name="'topics[' + index + '][content]'" required></textarea>
                    </div>
                </template>
                <fieldset class="flex flex-wrap gap-2" role="group" aria-labelledby="newsletter-actions">
                    <legend id="newsletter-actions" class="sr-only">Newsletter-Aktionen</legend>
                    <button type="button" class="btn-primary" @click="addTopic">Thema hinzufügen</button>
                    <button type="button" class="btn-primary" @click="confirmSend()">Versenden</button>
                    <button type="button" class="btn-primary" @click="confirmSend(true)">Newsletter testen</button>
                </fieldset>
                <input type="hidden" name="test" value="0" x-ref="test" />

                <div x-show="showConfirm" x-cloak class="fixed inset-0 flex items-center justify-center bg-black/50">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg">
                        <p class="mb-4 text-gray-800 dark:text-gray-200" x-text="pendingTest ? 'Testnewsletter wirklich versenden?' : 'Wirklich Newsletter versenden?'"></p>
                        <div class="flex justify-end">
                            <button type="button" class="btn-secondary" @click="showConfirm = false">Abbrechen</button>
                            <button type="button" class="ml-2 btn-primary" @click="submit">Ja, versenden</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
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
