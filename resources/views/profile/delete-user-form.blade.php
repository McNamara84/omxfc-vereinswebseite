<x-action-section>
    <x-slot name="title">
        {{ __('Mitgliedschaft kündigen') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Dies beendet automatisch deine Mitglieschaft im OMXFC e. V. und löscht auch deinen Account für den internen Mitgliederbereich.') }}
    </x-slot>

    <x-slot name="content">
        <div class="max-w-xl text-sm text-gray-600 dark:text-gray-400">
            {{ __('Wenn du deine Mitgliedschaft kündigst, werden sämtliche Daten von dir aus dem System gelöscht. Du hast dann keinen Zugang mehr zum internen Mitgliederbereich. Informationen, die du behalten möchtest, solltest du vorher sichern.') }}
        </div>

        <div class="mt-5">
            <x-danger-button wire:click="confirmUserDeletion" wire:loading.attr="disabled">
                {{ __('Mitgliedschaft kündigen') }}
            </x-danger-button>
        </div>

        <!-- Delete User Confirmation Modal -->
        <x-dialog-modal wire:model.live="confirmingUserDeletion">
            <x-slot name="title">
                {{ __('Mitgliedschaft kündigen') }}
            </x-slot>

            <x-slot name="content">
                {{ __('Bist du sicher, dass du deine Mitgliedschaft wirklich beenden möchtest? Dies löscht deinen Account dauerhaft und kann nicht rückgängig gemacht werden. Bitte gib zur Sicherheit dein Passwort ein.') }}

                <div class="mt-4" x-data="{}" x-on:confirming-delete-user.window="setTimeout(() => $refs.password.focus(), 250)">
                    <x-input type="password" class="mt-1 block w-3/4"
                                autocomplete="current-password"
                                placeholder="{{ __('Passwort') }}"
                                x-ref="password"
                                wire:model="password"
                                wire:keydown.enter="deleteUser" />

                    <x-input-error for="password" class="mt-2" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('confirmingUserDeletion')" wire:loading.attr="disabled">
                    {{ __('Abbrechen') }}
                </x-secondary-button>

                <x-danger-button class="ms-3" wire:click="deleteUser" wire:loading.attr="disabled">
                    {{ __('Mitgliedschaft beenden') }}
                </x-danger-button>
            </x-slot>
        </x-dialog-modal>
    </x-slot>
</x-action-section>
