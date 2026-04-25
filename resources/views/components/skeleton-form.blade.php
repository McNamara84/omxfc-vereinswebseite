@props([
    'fields' => 6,
    'hasTextarea' => true,
])

<div class="card bg-base-100 shadow-sm max-w-2xl mx-auto" aria-hidden="true">
    <div class="card-body gap-4">
        {{-- Überschrift --}}
        <div class="skeleton h-7 w-48"></div>
        <div class="divider my-0"></div>

        @for ($i = 0; $i < $fields; $i++)
            <fieldset class="fieldset">
                <div class="skeleton h-4 w-24 mb-1"></div>
                <div class="skeleton h-10 w-full rounded"></div>
            </fieldset>
        @endfor

        @if ($hasTextarea)
            <fieldset class="fieldset">
                <div class="skeleton h-4 w-32 mb-1"></div>
                <div class="skeleton h-24 w-full rounded"></div>
            </fieldset>
        @endif

        {{-- Buttons --}}
        <div class="flex gap-3 mt-2">
            <div class="skeleton h-10 w-32 rounded"></div>
            <div class="skeleton h-10 w-24 rounded"></div>
        </div>
    </div>
</div>
