@props(['offer'])

<x-alert icon="o-megaphone" class="mb-6 border-2 border-warning/60 bg-warning/10 shadow-lg alert-warning">
    <div class="space-y-1">
        <p class="text-lg font-black leading-tight">{{ $offer['banner_text'] }}</p>
        @if(!empty($offer['banner_end_text']))
            <p class="text-sm font-semibold">{{ $offer['banner_end_text'] }}</p>
        @endif
        <p class="text-sm opacity-90">Aktuelle Aktionsregel: {{ $offer['rule_label'] }}</p>
    </div>
</x-alert>