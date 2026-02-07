@props(['id' => null, 'maxWidth' => null])

<x-modal :id="$id" :maxWidth="$maxWidth" {{ $attributes }}>
    <div class="px-6 py-4">
        <div class="text-lg font-medium text-base-content">
            {{ $title }}
        </div>

        <div class="mt-4 text-sm text-base-content/70">
            {{ $content }}
        </div>
    </div>

    <div class="flex flex-row justify-end px-6 py-4 bg-base-200 text-end">
        {{ $footer }}
    </div>
</x-modal>
