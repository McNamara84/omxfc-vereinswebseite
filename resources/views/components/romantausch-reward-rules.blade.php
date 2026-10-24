@props(['rules'])

@foreach($rules as $rule)
    <span class="block"><strong>{{ $rule['label'] }}:</strong> {{ $rule['description'] }}</span>
@endforeach
