@props(['name'])
<div data-feature="{{ $name }}" x-data x-ignore>
    <p data-feature-status role="status">Wird geladen …</p>
    <button type="button" class="btn btn-outline" data-feature-retry hidden>Erneut versuchen</button>
    <div data-feature-content hidden inert>{{ $slot }}</div>
</div>
