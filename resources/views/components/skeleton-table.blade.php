@props([
    'columns' => 5,
    'rows' => 6,
    'hasAvatar' => false,
])

@php $widths = ['w-16', 'w-20', 'w-24', 'w-28', 'w-32']; @endphp
<div class="overflow-x-auto" aria-hidden="true">
    <table class="table">
        <thead>
            <tr>
                @for ($i = 0; $i < $columns; $i++)
                    <th><div class="skeleton h-4 w-20"></div></th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @for ($r = 0; $r < $rows; $r++)
                <tr>
                    @for ($c = 0; $c < $columns; $c++)
                        <td>
                            @if ($hasAvatar && $c === 0)
                                <div class="flex items-center gap-3">
                                    <div class="skeleton h-10 w-10 shrink-0 rounded-full"></div>
                                    <div class="flex flex-col gap-1">
                                        <div class="skeleton h-4 w-24"></div>
                                        <div class="skeleton h-3 w-16"></div>
                                    </div>
                                </div>
                            @else
                                <div class="skeleton h-4 {{ $widths[($r + $c) % count($widths)] }}"></div>
                            @endif
                        </td>
                    @endfor
                </tr>
            @endfor
        </tbody>
    </table>
</div>
