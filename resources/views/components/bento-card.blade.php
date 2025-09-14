@props(['title', 'href' => null, 'srText' => null])
@php
    $titleId = \Illuminate\Support\Str::slug($title, '-') . '-' . uniqid();
@endphp
@if($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 flex flex-col hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-200']) }} role="region" aria-labelledby="{{ $titleId }}">
@else
<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 flex flex-col']) }} role="region" aria-labelledby="{{ $titleId }}">
@endif
    <h2 id="{{ $titleId }}" class="text-lg font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-2">{{ $title }}</h2>
    <div class="mt-auto">
        {{ $slot }}
    </div>
    @if($srText)
        <span class="sr-only">{{ $srText }}</span>
    @endif
    @isset($actions)
        <div class="mt-4 flex gap-2" aria-label="{{ __('Aktionen für :title', ['title' => $title]) }}">
            {{ $actions }}
        </div>
    @endisset
@if($href)
</a>
@else
</div>
@endif
