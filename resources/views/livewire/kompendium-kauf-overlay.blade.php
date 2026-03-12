{{-- resources/views/livewire/kompendium-kauf-overlay.blade.php --}}
<div>
    @if($purchased)
        <x-alert icon="o-check-circle" class="alert-success mb-4" data-testid="kompendium-purchase-success">
            Kompendium freigeschaltet! Die Seite wird neu geladen …
        </x-alert>
        <script>
            setTimeout(() => window.location.reload(), 1500);
        </script>
    @else
        <x-alert icon="o-lock-closed" class="alert-warning mb-4" data-testid="kompendium-purchase-overlay">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <p>Die Suche kostet <strong>{{ $this->costBaxx }} Baxx</strong>.</p>
                    <p class="text-sm">Dein Guthaben: <strong>{{ $this->availableBaxx }} Baxx</strong></p>
                </div>

                @if($this->availableBaxx >= $this->costBaxx)
                    <x-button
                        label="Jetzt freischalten"
                        icon="o-lock-open"
                        wire:click="purchase"
                        wire:loading.attr="disabled"
                        class="btn-primary btn-sm"
                        data-testid="kompendium-purchase-button"
                    />
                @else
                    <p class="text-sm text-base-content/60">
                        Du benötigst noch {{ $this->costBaxx - $this->availableBaxx }} Baxx.
                    </p>
                @endif
            </div>

            @if($errorMessage)
                <p class="text-error text-sm mt-2">{{ $errorMessage }}</p>
            @endif

            <x-loading wire:loading wire:target="purchase" class="loading-spinner loading-sm" />
        </x-alert>
    @endif
</div>
