@if($showGovernanceTools && $pendingVerification > 0)
    <a href="{{ route('todos.index') }}?filter=pending" wire:navigate class="block" data-testid="dashboard-pending-panel">
        <x-ui.panel>
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-base-content/45">Moderation</p>
                    <h2 class="font-display text-2xl font-semibold tracking-tight text-base-content">Auf Verifizierung wartende Challenges</h2>
                    <p class="text-sm text-base-content/72">Es gibt {{ $pendingVerification }} Challenge(s), die auf Bestätigung warten.</p>
                </div>

                <div class="flex items-center gap-4">
                    <div class="font-display text-4xl font-bold tracking-tight text-primary">{{ $pendingVerification }}</div>
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary ring-1 ring-primary/15">
                        <x-icon name="o-chevron-right" class="h-6 w-6" />
                    </span>
                </div>
            </div>
        </x-ui.panel>
    </a>
@endif