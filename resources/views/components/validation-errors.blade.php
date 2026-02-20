@if ($errors->any())
    <x-alert icon="o-exclamation-triangle" class="alert-error" {{ $attributes }}>
        <div class="font-medium">{{ __('Es gibt ein Problem bei der Anmeldung.') }}</div>
        <ul class="mt-2 list-disc list-inside text-sm">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
