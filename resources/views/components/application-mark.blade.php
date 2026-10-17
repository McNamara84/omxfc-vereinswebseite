@php
	$logoSrc = rescue(
		fn () => Vite::asset('resources/images/omxfc-logo.png'),
		'data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA='
	);
@endphp

<img src="{{ $logoSrc }}" alt="{{ config('app.name') }}" {{ $attributes->class(['h-9 w-auto shrink-0']) }}>
