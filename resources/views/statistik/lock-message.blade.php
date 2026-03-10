{{-- resources/views/statistik/lock-message.blade.php --}}
@php
    $slug = 'statistik-' . $sectionId;
    $reward = $statistikRewards->get($slug);
    $isLocked = $reward && !in_array($slug, $unlockedSlugs);
@endphp

@if($isLocked)
    @livewire('statistik-kauf-overlay', [
        'rewardId' => $reward->id,
        'costBaxx' => $reward->cost_baxx,
        'availableBaxx' => $availableBaxx,
        'sectionId' => $sectionId,
    ], key('overlay-' . $sectionId))
@endif

