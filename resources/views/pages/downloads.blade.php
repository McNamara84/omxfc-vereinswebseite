<x-app-layout title="Downloads – Offizieller MADDRAX Fanclub e. V." description="Exklusive Dateien wie Bauanleitungen und Fanstories für Vereinsmitglieder.">
    <x-member-page class="max-w-4xl">
        <x-header title="Downloads" separator />

        @if($downloads->isEmpty())
            <x-alert icon="o-information-circle" class="alert-info">
                Aktuell sind keine Downloads verfügbar.
            </x-alert>
        @else
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
                                    @foreach($files as $download)
                                        <li class="flex items-center justify-between">
                                            <span>
                                                {{ $download->title }}
                                                @if($download->description)
                                                    <span class="text-base-content/60 text-sm block">{{ $download->description }}</span>
                                                @endif
                                                @if($download->formatted_file_size !== '–')
                                                    <x-badge :value="$download->formatted_file_size" class="badge-ghost badge-sm ml-1" />
                                                @endif
                                            </span>

                                            @if(in_array($download->id, $unlockedDownloadIds) || $download->rewards->isEmpty())
                                                <x-button label="Herunterladen" link="{{ route('downloads.download', $download) }}" icon="o-arrow-down-tray" class="btn-ghost btn-sm text-primary" />
                                            @else
                                                <a href="{{ route('rewards.index') }}" class="flex items-center text-base-content hover:text-primary" title="Unter Belohnungen freischalten">
                                                    <x-icon name="o-lock-closed" class="w-4 h-4 mr-1" />
                                                    <span class="text-sm">Freischalten</span>
                                                </a>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endif
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
