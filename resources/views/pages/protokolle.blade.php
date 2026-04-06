<x-app-layout title="Protokolle – Offizieller MADDRAX Fanclub e. V." description="Versammlungsprotokolle als PDF zum Download.">
    <x-member-page class="max-w-4xl">
        <x-header title="Protokolle" useH1 data-testid="page-title" />

        <x-card shadow>
            <div id="accordion">
                @foreach($protokolle as $jahr => $dokumente)
                    <details class="mb-4 border border-base-content/10 rounded-lg"
                             data-protokolle-accordion-item
                             x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }"
                             x-on:toggle="open = $el.open"
                             @if($loop->first) open @endif>
                        <summary
                            id="accordion-trigger-{{ $jahr }}"
                            class="list-none w-full flex justify-between items-center gap-4 bg-base-200 px-4 py-3 rounded-t-lg font-semibold text-left cursor-pointer focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                            :aria-expanded="open ? 'true' : 'false'"
                            aria-controls="content-{{ $jahr }}"
                            role="button"
                        >
                            <span class="flex flex-col sm:flex-row sm:items-center sm:gap-2">
                                <span>Protokolle {{ $jahr }}</span>
                                <x-badge :value="count($dokumente) . ' ' . (count($dokumente) === 1 ? 'Dokument' : 'Dokumente')" class="badge-ghost badge-sm" />
                            </span>
                            <span class="flex items-center gap-2 text-xl" aria-hidden="true">
                                <span class="select-none" x-text="open ? '−' : '+'">+</span>
                            </span>
                            <span class="sr-only">Abschnitt Protokolle {{ $jahr }} umschalten</span>
                        </summary>

                        <div
                            id="content-{{ $jahr }}"
                            class="bg-base-100 px-4 py-2 rounded-b-lg"
                            role="region"
                            aria-labelledby="accordion-trigger-{{ $jahr }}"
                            :aria-hidden="open ? 'false' : 'true'"
                        >
                            <ul class="space-y-2">
                                @foreach($dokumente as $protokoll)
                                    <li>
                                        <a
                                            href="{{ route('protokolle.download', $protokoll['datei']) }}" wire:navigate
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex items-center gap-2 text-primary hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                            x-data
                                            @click="$dispatch('mary-toast', { title: 'Download gestartet', description: 'Die PDF-Datei wird heruntergeladen…', position: 'toast-bottom toast-end', icon: 'o-arrow-down-tray', css: 'alert-info', timeout: 3000 })"
                                        >
                                            <x-icon name="o-document-text" class="w-4 h-4" />
                                            <span>{{ $protokoll['datum'] }} – {{ $protokoll['titel'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </details>
                @endforeach
            </div>
        </x-card>
    </x-member-page>
</x-app-layout>
