<x-app-layout title="Zwei-Faktor-Authentifizierung – Offizieller MADDRAX Fanclub e. V." description="Bestätige den Zugang zu deinem Konto mit deinem Authentifizierungscode.">
    <div class="max-w-md mx-auto px-6 py-12">
        <x-card shadow data-testid="two-factor-card">
            <x-header title="Zwei-Faktor-Authentifizierung" class="mb-4" useH1 />

            <div x-data="{ recovery: false }">
                <p class="mb-4 text-sm text-base-content/70" x-show="! recovery">
                    Bitte bestätige den Zugang zu deinem Konto, indem du den Authentifizierungscode aus deiner Authenticator-App eingibst.
                </p>

                <p class="mb-4 text-sm text-base-content/70" x-cloak x-show="recovery">
                    Bitte bestätige den Zugang zu deinem Konto, indem du einen deiner Wiederherstellungscodes eingibst.
                </p>

                @if ($errors->any())
                    <x-alert icon="o-exclamation-triangle" class="alert-error mb-4">
                        <div class="font-medium">{{ __('Es gibt ein Problem.') }}</div>
                        <ul class="mt-2 list-disc list-inside text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif

                <form method="POST" action="{{ route('two-factor.login') }}">
                    @csrf

                    <div x-show="! recovery">
                        <x-input
                            label="Code"
                            id="code"
                            name="code"
                            type="text"
                            inputmode="numeric"
                            autofocus
                            autocomplete="one-time-code"
                            data-testid="two-factor-code"
                        />
                    </div>

                    <div x-cloak x-show="recovery">
                        <x-input
                            label="Wiederherstellungscode"
                            id="recovery_code"
                            name="recovery_code"
                            type="text"
                            autocomplete="one-time-code"
                            data-testid="two-factor-recovery-code"
                        />
                    </div>

                    <div class="flex items-center justify-end mt-6">
                        <x-button
                            label="Wiederherstellungscode verwenden"
                            type="button"
                            class="btn-ghost btn-sm"
                            x-show="! recovery"
                            x-on:click.prevent="
                                recovery = true;
                                $nextTick(() => { document.getElementById('recovery_code')?.focus() })
                            "
                            data-testid="two-factor-use-recovery"
                        />

                        <x-button
                            label="Authentifizierungscode verwenden"
                            type="button"
                            class="btn-ghost btn-sm"
                            x-cloak
                            x-show="recovery"
                            x-on:click.prevent="
                                recovery = false;
                                $nextTick(() => { document.getElementById('code')?.focus() })
                            "
                            data-testid="two-factor-use-code"
                        />

                        <x-button label="Anmelden" type="submit" class="btn-primary ms-4" data-testid="two-factor-submit" />
                    </div>
                </form>
            </div>
        </x-card>
    </div>
</x-app-layout>
