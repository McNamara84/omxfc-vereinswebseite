@if ($errors->any())
    <div {{ $attributes }}>
        <div class="font-medium text-error">{{ __('Es gibt ein Problem bei der Anmeldung.') }}</div>

        <ul class="mt-3 list-disc list-inside text-sm text-error">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
