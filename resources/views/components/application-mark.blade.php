@php
    try {
        $logo = Vite::asset('resources/images/omxfc-logo.png');
    } catch (Throwable $e) {
        $logo = asset('resources/images/omxfc-logo.png');
    }
@endphp
<img src="{{ $logo }}" alt="{{ config('app.name') }}" class="h-9 w-auto">
