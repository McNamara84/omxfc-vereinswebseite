<x-form-section submit="save">
    <x-slot name="title">
        {{ __('Benachrichtigungen') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Möchtest du bei neuen Rezensionen zu deinen Romanen per E-Mail benachrichtigt werden?') }}
    </x-slot>

    <x-slot name="form">
        <div class="col-span-6 sm:col-span-4">
            <label for="notify_new_review" class="flex items-center">
                <x-checkbox id="notify_new_review" wire:model="notifyNewReview" />
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('E-Mail bei neuer Rezension erhalten') }}
                </span>
            </label>
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            {{ __('Gespeichert.') }}
        </x-action-message>
        <x-button>
            {{ __('Speichern') }}
        </x-button>
    </x-slot>
</x-form-section>