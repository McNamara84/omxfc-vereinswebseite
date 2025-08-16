@props(['action'])

<div x-data="{ open: false }" class="inline-block ml-2">
    <button type="button" @click="open = true" class="text-red-600 dark:text-red-400 hover:underline">
        {{ __('Löschen') }}
    </button>

    <div x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
            <p class="mb-4 text-gray-800 dark:text-gray-200">{{ __('Wirklich löschen?') }}</p>
            <div class="flex justify-end space-x-2">
                <button type="button" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded" @click="open = false">
                    {{ __('Abbrechen') }}
                </button>
                <form method="POST" action="{{ $action }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded">
                        {{ __('Löschen') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
