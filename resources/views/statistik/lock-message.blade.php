{{-- resources/views/statistik/lock-message.blade.php --}}
@php
    $slug = 'statistik-' . $sectionId;
    $reward = $statistikRewards->get($slug);
    $isLocked = !in_array($slug, $unlockedSlugs, true);
@endphp

@if($isLocked)
    @if($reward && $reward->is_active)
        @livewire('statistik-kauf-overlay', [
            'rewardId' => $reward->id,
            'costBaxx' => $reward->cost_baxx,
            'availableBaxx' => $availableBaxx,
            'sectionId' => $sectionId,
        ], key('overlay-' . $sectionId))
    @else
        <div class="absolute inset-0 flex flex-col items-center justify-center bg-base-100/70 backdrop-blur-sm text-sm text-base-content text-center space-y-2 z-10">
            <x-icon name="o-lock-closed" class="w-6 h-6 text-base-content/50" />
            <p>Diese Statistik ist derzeit nicht verfügbar.</p>
        </div>
    @endif
@endif

