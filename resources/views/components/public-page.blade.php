@props(['surface' => false])
<div {{ $attributes->class(['omxfc-public-page mx-auto w-full max-w-6xl px-4 py-4 sm:px-6 sm:py-6', 'rounded-2xl border border-base-content/10 bg-base-100' => $surface]) }}>
    {{ $slot }}
</div>

