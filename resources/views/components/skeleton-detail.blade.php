@props([
    'hasImage' => false,
    'sections' => 3,
])

<div class="card bg-base-100 shadow-sm max-w-4xl mx-auto" aria-hidden="true">
    <div class="card-body gap-4">
        {{-- Header --}}
        <div class="flex items-center gap-4">
            @if ($hasImage)
                <div class="skeleton h-20 w-20 rounded-lg shrink-0"></div>
            @endif
            <div class="flex flex-col gap-2 flex-1">
                <div class="skeleton h-7 w-64"></div>
                <div class="skeleton h-4 w-40"></div>
            </div>
        </div>

        <div class="divider my-0"></div>

        {{-- Content-Sektionen --}}
        @for ($i = 0; $i < $sections; $i++)
            <div class="flex flex-col gap-2">
                <div class="skeleton h-5 w-32"></div>
                <div class="skeleton h-4 w-full"></div>
                <div class="skeleton h-4 w-5/6"></div>
                <div class="skeleton h-4 w-3/4"></div>
            </div>
        @endfor

        {{-- Action-Buttons --}}
        <div class="flex gap-3 mt-4">
            <div class="skeleton h-10 w-28 rounded"></div>
            <div class="skeleton h-10 w-28 rounded"></div>
        </div>
    </div>
</div>
