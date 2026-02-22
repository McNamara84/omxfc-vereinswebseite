<div>
    <x-header title="{{ __('Browser-Sitzungen') }}" subtitle="{{ __('Verwalte und beende Browser-Sitzungen auf deinen unterschiedlichen Endgeräten.') }}" size="text-lg" class="!mb-4" />

    <div class="max-w-xl text-sm text-base-content">
        {{ __('Falls erforderlich, kannst du dich von allen anderen Browser-Sitzungen auf allen deinen Geräten abmelden. Einige deiner letzten Sitzungen sind unten aufgeführt; diese Liste ist jedoch möglicherweise nicht vollständig. Wenn du glauben solltest, dass dein Konto kompromittiert wurde, solltest du auch dein Passwort aktualisieren.') }}
    </div>

    @if (count($this->sessions) > 0)
        <div class="mt-5 space-y-6">
            <!-- Other Browser Sessions -->
            @foreach ($this->sessions as $session)
                <div class="flex items-center">
                    <div>
                        @if ($session->agent->isDesktop())
                            <x-icon name="o-computer-desktop" class="size-8 text-base-content" />
                        @else
                            <x-icon name="o-device-phone-mobile" class="size-8 text-base-content" />
                        @endif
                    </div>

                    <div class="ms-3">
                        <div class="text-sm text-base-content">
                            {{ $session->agent->platform() ? $session->agent->platform() : __('Unbekannt') }} - {{ $session->agent->browser() ? $session->agent->browser() : __('Unbekannt') }}
                        </div>

                        <div>
                            <div class="text-xs text-base-content">
                                {{ $session->ip_address }},

                                @if ($session->is_current_device)
                                    <span class="text-success font-semibold">{{ __('Dieses Gerät') }}</span>
                                @else
                                    {{ __('Zuletzt aktiv') }} {{ $session->last_active }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="flex items-center mt-5">
        <x-button class="btn-primary" wire:click="confirmLogout" wire:loading.attr="disabled">
            {{ __('Alle anderen Browser-Sitzungen beenden') }}
        </x-button>
    </div>

    <!-- Log Out Other Devices Confirmation Modal -->
    @if($confirmingLogout)
    <x-mary-modal wire:model="confirmingLogout" title="{{ __('Alle anderen Browser-Sitzungen beenden') }}" separator>
        <p class="text-base-content">
            {{ __('Bitte gib dein Passwort ein, um zu bestätigen, dass du dich von deinen anderen Browser-Sitzungen auf allen deinen Geräten abmelden möchten.') }}
        </p>

        <div class="mt-4">
            <x-input type="password" class="w-3/4"
                        placeholder="{{ __('Passwort') }}"
                        wire:model="password"
                        wire:keydown.enter="logoutOtherBrowserSessions" />
            @error('password')
                <p class="text-error text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Abbrechen') }}" wire:click="$toggle('confirmingLogout')" wire:loading.attr="disabled" />
            <x-button label="{{ __('Alle anderen Browser-Sitzungen beenden') }}" class="btn-primary" wire:click="logoutOtherBrowserSessions" wire:loading.attr="disabled" />
        </x-slot:actions>
    </x-mary-modal>
    @endif
</div>
