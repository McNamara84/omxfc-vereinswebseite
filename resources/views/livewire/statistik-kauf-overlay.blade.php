{{-- resources/views/livewire/statistik-kauf-overlay.blade.php --}}
<div>
    @if($purchased)
        {{-- Nach Kauf: kurze Bestätigung, dann Seite neu laden (analog zum Kompendium-Overlay) --}}
        <div class="absolute inset-0 flex items-center justify-center bg-success/20 backdrop-blur-sm z-10">
            <p class="text-success font-semibold text-lg">✓ Freigeschaltet!</p>
        </div>
        <script>
            setTimeout(() => window.location.reload(), 1500);
        </script>
    @else
        <div class="absolute inset-0 flex flex-col items-center justify-center bg-base-100/70 backdrop-blur-sm text-sm text-base-content text-center space-y-2 z-10">
            <x-icon name="o-lock-closed" class="w-6 h-6 text-base-content/50" />
            <p>Diese Statistik kostet <strong>{{ $this->costBaxx }} Baxx</strong>.</p>
            <p>Dein Guthaben: <span class="font-semibold">{{ $this->availableBaxx }} Baxx</span></p>

            @if($this->availableBaxx >= $this->costBaxx)
                <x-button
                    label="Jetzt freischalten"
                    icon="o-lock-open"
                    wire:click="purchase"
                    wire:loading.attr="disabled"
                    class="btn-primary btn-sm mt-1"
                    data-testid="statistik-purchase-{{ $sectionId }}"
                />
            @else
                <p class="text-xs text-base-content/60">
                    Du benötigst noch {{ $this->costBaxx - $this->availableBaxx }} Baxx.
                </p>
            @endif

            @if($errorMessage)
                <p class="text-error text-xs mt-1">{{ $errorMessage }}</p>
            @endif

            <x-loading wire:loading wire:target="purchase" class="loading-spinner loading-sm" />
        </div>
    @endif
</div>
