<div>
    <x-header title="{{ __('Benachrichtigungen') }}" subtitle="{{ __('Möchtest du bei neuen Rezensionen zu deinen Romanen per E-Mail benachrichtigt werden?') }}" size="text-lg" class="!mb-4" />

    <x-form wire:submit="save" class="max-w-xl">
        <x-checkbox id="notify_new_review" wire:model="notifyNewReview" label="{{ __('E-Mail bei neuer Rezension erhalten') }}" />

        <x-slot:actions>
            <x-button type="submit" class="btn-primary">
                {{ __('Speichern') }}
            </x-button>
        </x-slot:actions>
    </x-form>
</div>