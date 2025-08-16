<x-app-layout title="Newsletter versenden">
    <h1 class="text-2xl font-bold mb-4">Newsletter versenden</h1>
    @if(session('status'))
        <div class="mb-4 text-green-600">{{ session('status') }}</div>
    @endif
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
                    <label class="flex items-center">
                        {{-- Mitglied is the typical target audience and therefore pre-selected --}}
                        <input type="checkbox" name="roles[]" value="{{ $role }}" class="rounded" {{ $role === $defaultRole ? 'checked' : '' }}>
                        <span class="ml-2">{{ $role }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        <template x-for="(topic, index) in topics" :key="index">
            <div class="mb-4">
                <x-label x-bind:for="'topic-title-' + index" value="Thema" />
                <x-input x-bind:id="'topic-title-' + index" type="text" class="mt-1 block w-full" x-bind:name="'topics[' + index + '][title]'" required />
                <x-label x-bind:for="'topic-content-' + index" value="Text" class="mt-2" />
                <textarea x-bind:id="'topic-content-' + index" class="mt-1 block w-full border-gray-300 rounded-md" rows="3" x-bind:name="'topics[' + index + '][content]'" required></textarea>
            </div>
        </template>
        <x-button type="button" class="mt-2" @click="addTopic">Thema hinzufügen</x-button>
        <x-button type="button" class="mt-2 ml-2" @click="confirmSend">Versenden</x-button>
        <x-button type="submit" name="test" value="1" class="mt-2 ml-2">Newsletter testen</x-button>

        <div x-show="showConfirm" class="fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-75" x-cloak>
            <div class="bg-white dark:bg-gray-800 p-6 rounded shadow-lg">
                <p class="mb-4">Wirklich Newsletter versenden?</p>
                <div class="flex justify-end">
                    <x-secondary-button type="button" @click="showConfirm = false">Abbrechen</x-secondary-button>
                    <x-button type="button" class="ml-2" @click="$refs.form.submit()">Ja, versenden</x-button>
                </div>
            </div>
        </div>
    </form>
</x-app-layout>

<script>
function newsletterForm() {
    return {
        topics: [{}],
        showConfirm: false,
        addTopic() { this.topics.push({}); },
        confirmSend() { this.showConfirm = true; }
    }
}
</script>
