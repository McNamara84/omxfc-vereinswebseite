<div>
    <x-header title="{{ __('Mitgliedschaft kündigen') }}" subtitle="{{ __('Dies beendet automatisch deine Mitglieschaft im OMXFC e. V. und löscht auch deinen Account für den internen Mitgliederbereich.') }}" size="text-lg" class="!mb-4" />

    <div class="max-w-xl text-sm text-base-content">
        {{ __('Wenn du deine Mitgliedschaft kündigst, werden sämtliche Daten von dir aus dem System gelöscht. Du hast dann keinen Zugang mehr zum internen Mitgliederbereich. Informationen, die du behalten möchtest, solltest du vorher sichern.') }}
    </div>

    <div class="mt-5">
        <x-button class="btn-error" wire:click="confirmUserDeletion" wire:loading.attr="disabled">
            {{ __('Mitgliedschaft kündigen') }}
        </x-button>
    </div>

    <!-- Delete User Confirmation Modal -->
    @if($confirmingUserDeletion)
    <x-mary-modal wire:model="confirmingUserDeletion" title="{{ __('Mitgliedschaft kündigen') }}" separator>
        <p class="text-base-content">
            {{ __('Bist du sicher, dass du deine Mitgliedschaft wirklich beenden möchtest? Dies löscht deinen Account dauerhaft und kann nicht rückgängig gemacht werden. Bitte gib zur Sicherheit dein Passwort ein.') }}
        </p>

        <div class="mt-4">
            <x-input type="password" class="w-3/4"
                        placeholder="{{ __('Passwort') }}"
                        wire:model="password"
                        wire:keydown.enter="deleteUser" />
            @error('password')
                <p class="text-error text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Abbrechen') }}" wire:click="$toggle('confirmingUserDeletion')" wire:loading.attr="disabled" />
            <x-button label="{{ __('Mitgliedschaft beenden') }}" class="btn-error" wire:click="deleteUser" wire:loading.attr="disabled" />
        </x-slot:actions>
    </x-mary-modal>
    @endif
</div>
