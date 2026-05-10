@props([
    'headers' => [],
    'rows' => [],
    'striped' => false,
])

@php
    $items = $rows;

    if ($rows instanceof \Illuminate\Contracts\Pagination\Paginator) {
        $items = collect($rows->items());
    } elseif ($rows instanceof \Illuminate\Support\Collection) {
        $items = $rows;
    } else {
        $items = collect($rows);
    }

    $resolveCellValue = static function ($row, ?string $key) {
        if (! $key || $key === 'actions') {
            return '';
        }

        return match ($key) {
            'full_name' => data_get($row, 'full_name') ?? trim((string) data_get($row, 'vorname').' '.data_get($row, 'nachname')),
            'status' => data_get($row, 'ist_mitglied') ? 'Mitglied' : 'Gast',
            'orga_team' => data_get($row, 'orga_team') ? 'Ja' : 'Nein',
            'tshirt' => data_get($row, 'tshirt_bestellt') ? ('Ja'.(data_get($row, 'tshirt_groesse') ? ' ('.data_get($row, 'tshirt_groesse').')' : '')) : 'Nein',
            'zahlung' => data_get($row, 'payment_status'),
            'profil' => data_get($row, 'user_id') ? 'Profil' : '-',
            default => data_get($row, $key, ''),
        };
    };
@endphp

<div class="overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'table w-full']) }}>
        @if(! empty($headers))
            <thead>
                <tr>
                    @foreach($headers as $header)
                        <th class="{{ $header['class'] ?? '' }}">{{ $header['label'] ?? $header['key'] ?? '' }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif

        <tbody>
            @foreach($items as $row)
                <tr @class(['odd:bg-base-200/30' => $striped])>
                    @foreach($headers as $header)
                        @php($key = $header['key'] ?? null)
                        <td class="{{ $header['class'] ?? '' }}">{{ $resolveCellValue($row, $key) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($rows instanceof \Illuminate\Contracts\Pagination\Paginator && method_exists($rows, 'links'))
        <div class="mt-4">
            {{ $rows->links() }}
        </div>
    @endif
</div>