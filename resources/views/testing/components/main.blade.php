<div {{ $attributes->merge(['class' => 'w-full']) }}>
    {{ $content ?? $slot }}
</div>