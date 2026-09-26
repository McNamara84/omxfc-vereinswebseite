<div class="space-y-3">
    @foreach($changes as $change)
        <div class="rounded-lg border border-base-300 p-3">
            <p class="font-semibold">{{ $change['name'] }}@if($change['target']) · {{ $change['target'] }}@endif: {{ $change['before'] }} → {{ $change['after'] }} · {{ $change['cost'] }} EP</p>
            <p class="whitespace-pre-wrap break-words">{{ $change['reason'] }}</p>
            @if(! empty($change['languages']))<p>Sprachen: {{ implode(', ', $change['languages']) }}</p>@endif
            @if(! empty($change['items']))<p>Ausrüstung: {{ implode(', ', array_map(fn($id) => \App\Support\RpgCharEditorEquipment::itemMap()[$id]['name'] ?? $id, $change['items'])) }}</p>@endif
        </div>
    @endforeach
</div>
