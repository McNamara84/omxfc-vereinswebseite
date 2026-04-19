@props([
    'cols' => 3,
    'rows' => 4,
])

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-{{ $cols }} gap-4" aria-hidden="true">
    @for ($i = 0; $i < $rows * $cols; $i++)
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <div class="skeleton h-32 w-full rounded"></div>
                <div class="skeleton h-5 w-3/4"></div>
                <div class="skeleton h-4 w-1/2"></div>
                <div class="flex gap-2 mt-2">
                    <div class="skeleton h-8 w-20 rounded"></div>
                    <div class="skeleton h-8 w-16 rounded"></div>
                </div>
            </div>
        </div>
    @endfor
</div>
