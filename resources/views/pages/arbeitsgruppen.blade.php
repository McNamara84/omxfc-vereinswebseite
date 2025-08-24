<x-app-layout title="Arbeitsgruppen – Offizieller MADDRAX Fanclub e. V." description="Überblick über alle Projektteams des Vereins.">
    <x-public-page>
        <h1 class="text-2xl sm:text-3xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-4 sm:mb-8">Arbeitsgruppen des OMXFC e.V.</h1>

        @foreach($ags as $ag)
            <section class="mb-12">
                <h2 class="text-2xl font-semibold mb-4">{{ $ag->name }}</h2>
                @if($ag->logo_path)
                    <img loading="lazy" src="{{ asset('storage/' . $ag->logo_path) }}" alt="Logo der {{ $ag->name }}" class="w-full h-auto rounded-lg shadow mb-4 ag-logo">
                @endif
                @if($ag->description)
                    <p class="mb-4">{{ $ag->description }}</p>
                @endif
                <p>
                    @if($ag->owner)
                        <strong>AG-Leitung:</strong> {{ $ag->owner->name }}<br>
                    @endif
                    @if($ag->meeting_schedule)
                        <strong>Treffen:</strong> {{ $ag->meeting_schedule }}<br>
                    @endif
                    @if($ag->email)
                        <strong>Kontakt:</strong> <a href="mailto:{{ $ag->email }}" class="text-blue-600 hover:underline">{{ $ag->email }}</a>
                    @endif
                </p>
            </section>
        @endforeach
    </x-public-page>
</x-app-layout>
