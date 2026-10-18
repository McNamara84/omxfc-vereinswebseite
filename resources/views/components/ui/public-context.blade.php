@if(app(\App\Support\Navigation\NavigationContext::class)->canAccessMembers(auth()->user()))
    <div class="border-b border-base-content/10 bg-base-100" data-testid="public-context">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-2 text-sm sm:px-6 lg:px-8">
            <span class="text-base-content/80">Öffentliche Website</span>
            <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex min-h-9 items-center gap-2 rounded-md font-semibold text-primary underline-offset-4 hover:underline">Zurück zum Mitgliederbereich <span aria-hidden="true">→</span></a>
        </div>
    </div>
@endif
