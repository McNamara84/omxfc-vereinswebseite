<x-app-layout title="Downloads – Offizieller MADDRAX Fanclub e. V." description="Exklusive Dateien wie Bauanleitungen und Fanstories für Vereinsmitglieder.">
    <x-member-page class="max-w-4xl">
        <x-header title="Downloads">
            <x-slot:subtitle>
                Deine Baxx: <x-badge :value="$userPoints" class="badge-primary" />
            </x-slot:subtitle>
        </x-header>

        <x-card shadow>
            <div id="accordion">
                @foreach($downloads as $kategorie => $files)
                    @php $id = \Illuminate\Support\Str::slug($kategorie); @endphp

                    <div class="mb-4 border border-base-content/10 rounded-lg">
                        <h2>
                            <button
                                type="button"
                                class="w-full flex justify-between items-center bg-base-200 px-4 py-3 rounded-t-lg font-semibold"
                                onclick="toggleAccordion('{{ $id }}')"
                            >
                                {{ $kategorie }}
                                <span id="icon-{{ $id }}">+</span>
                            </button>
                        </h2>

                        <div id="content-{{ $id }}" class="hidden bg-base-100 px-4 py-2 rounded-b-lg">
                            <ul class="space-y-2">
                                @foreach($files as $file)
                                    <li class="flex items-center justify-between">
                                        <span>
                                            {{ $file['titel'] }}
                                            <x-badge :value="$file['punkte'] . ' Baxx'" class="badge-ghost badge-sm ml-2" />
                                        </span>

                                        @if($userPoints >= $file['punkte'])
                                            <x-button label="Herunterladen" link="{{ route('downloads.download', $file['datei']) }}" icon="o-arrow-down-tray" class="btn-ghost btn-sm text-primary" />
                                        @else
                                            <span class="flex items-center text-base-content" title="Mehr Baxx nötig">
                                                <x-icon name="o-lock-closed" class="w-4 h-4 mr-1" />
                                                Gesperrt
                                            </span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    </x-member-page>

    <script>
        function toggleAccordion(id) {
            const content = document.getElementById('content-' + id);
            const icon    = document.getElementById('icon-' + id);
            content.classList.toggle('hidden');
            icon.textContent = content.classList.contains('hidden') ? '+' : '-';
        }
    </script>
</x-app-layout>
