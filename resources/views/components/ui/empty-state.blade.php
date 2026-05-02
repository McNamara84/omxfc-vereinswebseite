@props([
    'title' => null,
    'description' => null,
    'icon' => 'o-information-circle',
    'role' => 'status',
    'live' => 'polite',
])

<div
    {{ $attributes->class(['rounded-[1.5rem] border border-dashed border-base-content/12 bg-base-200/55 px-5 py-5']) }}
    role="{{ $role }}"
    aria-live="{{ $live }}"
>
    <div class="flex items-start gap-3">
        @if($icon)
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-base-100 text-primary ring-1 ring-base-content/10">
                <x-icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif

        <div class="space-y-1">
            @if($title)
                <h3 class="font-display text-lg font-semibold tracking-tight text-base-content">{{ $title }}</h3>
            @endif

            <p class="text-sm leading-relaxed text-base-content/72">
                {{ $description ?? $slot }}
            </p>
        </div>
    </div>
</div>