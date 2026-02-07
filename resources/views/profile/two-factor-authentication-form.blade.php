<div>
    <x-header title="{{ __('Zwei-Faktor-Authentisierung') }}" subtitle="{{ __('Für eine höhere Sicherheit empfiehlt der Vorstand eine Zwei-Faktor-Authentisierung einzurichten') }}" size="text-lg" class="!mb-4" />

        <h3 class="text-lg font-medium text-base-content">
            @if ($this->enabled)
                @if ($showingConfirmation)
                    {{ __('Finish enabling two factor authentication.') }}
                @else
                    {{ __('You have enabled two factor authentication.') }}
                @endif
            @else
                {{ __('Du hast die Zwei-Faktor-Authentifizierung nicht aktiviert.') }}
            @endif
        </h3>

        <div class="mt-3 max-w-xl text-sm text-base-content/70">
            <p>
                {{ __('Wenn die Zwei-Faktor-Authentifizierung aktiviert ist, wirst du während des Logins zur Eingabe eines sicheren, zufälligen Codes aufgefordert. Du kannst diesen Code über die Google Authenticator App deines Telefons abrufen.') }}
            </p>
        </div>

        @if ($this->enabled)
            @if ($showingQrCode)
                <div class="mt-4 max-w-xl text-sm text-base-content/70">
                    <p class="font-semibold">
                        @if ($showingConfirmation)
                            {{ __('Um die Aktivierung der Zwei-Faktor-Authentifizierung abzuschließen, scanne den folgenden QR-Code mit der Authenticator App deines Telefons oder gebe den Einrichtungsschlüssel und den generierten OTP-Code ein.') }}
                        @else
                            {{ __('Die Zwei-Faktor-Authentifizierung ist jetzt aktiviert. Scanne den folgenden QR-Code mit der Authenticator App deines Telefons oder gebe den Einrichtungsschlüssel manuell ein.') }}
                        @endif
                    </p>
                </div>

                <div class="mt-4 p-2 inline-block bg-white">
                    {!! $this->user->twoFactorQrCodeSvg() !!}
                </div>

                <div class="mt-4 max-w-xl text-sm text-base-content/70">
                    <p class="font-semibold">
                        {{ __('Setup Key') }}: {{ decrypt($this->user->two_factor_secret) }}
                    </p>
                </div>

                @if ($showingConfirmation)
                    <div class="mt-4">
                        <x-input
                            id="code"
                            label="{{ __('Code') }}"
                            type="text"
                            name="code"
                            class="block mt-1 w-1/2"
                            inputmode="numeric"
                            autofocus
                            autocomplete="one-time-code"
                            wire:model="code"
                            wire:keydown.enter="confirmTwoFactorAuthentication"
                        />
                    </div>
                @endif
            @endif

            @if ($showingRecoveryCodes)
                <div class="mt-4 max-w-xl text-sm text-base-content/70">
                    <p class="font-semibold">
                        {{ __('Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two factor authentication device is lost.') }}
                    </p>
                </div>

                <div class="grid gap-1 max-w-xl mt-4 px-4 py-4 font-mono text-sm bg-base-200 text-base-content rounded-lg">
                    @foreach (json_decode(decrypt($this->user->two_factor_recovery_codes), true) as $code)
                        <div>{{ $code }}</div>
                    @endforeach
                </div>
            @endif
        @endif

        <div class="mt-5">
            @if (! $this->enabled)
                <x-confirms-password wire:then="enableTwoFactorAuthentication">
                    <x-button type="button" class="btn-primary" wire:loading.attr="disabled">
                        {{ __('Einschalten') }}
                    </x-button>
                </x-confirms-password>
            @else
                @if ($showingRecoveryCodes)
                    <x-confirms-password wire:then="regenerateRecoveryCodes">
                        <x-button class="btn-ghost me-3">
                            {{ __('Wiederherstellungscodes neu erstellen') }}
                        </x-button>
                    </x-confirms-password>
                @elseif ($showingConfirmation)
                    <x-confirms-password wire:then="confirmTwoFactorAuthentication">
                        <x-button type="button" class="btn-primary me-3" wire:loading.attr="disabled">
                            {{ __('Bestätigen') }}
                        </x-button>
                    </x-confirms-password>
                @else
                    <x-confirms-password wire:then="showRecoveryCodes">
                        <x-button class="btn-ghost me-3">
                            {{ __('Wiederherstellungscodes anzeigen') }}
                        </x-button>
                    </x-confirms-password>
                @endif

                @if ($showingConfirmation)
                    <x-confirms-password wire:then="disableTwoFactorAuthentication">
                        <x-button class="btn-ghost" wire:loading.attr="disabled">
                            {{ __('Abbrechen') }}
                        </x-button>
                    </x-confirms-password>
                @else
                    <x-confirms-password wire:then="disableTwoFactorAuthentication">
                        <x-button class="btn-error" wire:loading.attr="disabled">
                            {{ __('Deaktivieren') }}
                        </x-button>
                    </x-confirms-password>
                @endif

            @endif
        </div>
</div>
