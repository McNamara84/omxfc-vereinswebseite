<x-app-layout title="Downloads – Offizieller MADDRAX Fanclub e. V." description="Exklusive Dateien wie Bauanleitungen und Fanstories für Vereinsmitglieder.">
    <x-member-page class="max-w-6xl space-y-8">
        @php
            $allDownloads = $downloads->flatMap(fn ($files) => $files)->values();
            $categoryCount = $downloads->count();
            $downloadCount = $allDownloads->count();
            $freeDownloadCount = $allDownloads->filter(fn ($download) => $download->reward === null)->count();
            $rewardDownloadCount = $allDownloads->filter(fn ($download) => $download->reward !== null)->count();
        @endphp

        <x-ui.page-header
            eyebrow="Exklusive Inhalte für Mitglieder"
            title="Downloads"
            description="Hier findest du Bauanleitungen, Fanstories und weitere Dateien aus dem Vereinsbereich. Gesperrte Inhalte lassen sich direkt über Belohnungen freischalten."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">{{ $downloadCount }} Dateien</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">{{ $categoryCount }} Kategorien</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">{{ $freeDownloadCount }} direkt verfügbar</span>
                    @if($rewardDownloadCount > 0)
                        <span class="badge badge-outline rounded-full px-3 py-3">{{ $rewardDownloadCount }} per Belohnung</span>
                    @endif
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.78fr)] xl:items-start">
            @if($downloads->isEmpty())
                <x-ui.panel title="Aktuell keine Downloads" description="Sobald neue Dateien veröffentlicht werden, erscheinen sie hier automatisch im passenden Bereich.">
                    <x-alert icon="o-information-circle" class="alert-info">
                        Aktuell sind keine Downloads verfügbar.
                    </x-alert>
                </x-ui.panel>
            @else
                <x-ui.panel title="Download-Bibliothek" description="Alle Inhalte sind thematisch gruppiert. Die erste Kategorie ist direkt geöffnet, weitere Bereiche kannst du nach Bedarf aufklappen.">
                    <div class="space-y-4">
                        @foreach($downloads as $kategorie => $files)
                            <details class="group overflow-hidden rounded-[1.5rem] border border-base-content/10 bg-base-100/72 shadow-sm shadow-base-content/5" @if($loop->first) open @endif>
                                <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-4 px-5 py-4 text-left">
                                    <div class="space-y-1">
                                        <h2 class="font-display text-xl font-semibold tracking-tight text-base-content">{{ $kategorie }}</h2>
                                        <p class="text-sm leading-relaxed text-base-content/60">{{ $files->count() }} {{ \Illuminate\Support\Str::plural('Datei', $files->count()) }} in diesem Bereich</p>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <span class="badge badge-outline rounded-full px-3 py-3">{{ $files->count() }}</span>
                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-base-200/70 text-base-content/58 transition-transform group-open:-rotate-180" aria-hidden="true">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 9 6 6 6-6" />
                                            </svg>
                                        </span>
                                    </div>
                                </summary>

                                <div class="border-t border-base-content/10 px-4 py-4 sm:px-5">
                                    <ul class="space-y-3">
                                        @foreach($files as $download)
                                            <li class="flex flex-col gap-4 rounded-[1.25rem] border border-base-content/10 bg-base-100/80 px-4 py-4 lg:flex-row lg:items-start lg:justify-between">
                                                <div class="space-y-2">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <h3 class="text-base font-semibold text-base-content sm:text-lg">{{ $download->title }}</h3>

                                                        @if($download->file_size !== null)
                                                            <x-badge :value="$download->formatted_file_size" class="badge-ghost badge-sm" icon="o-document" />
                                                        @endif

                                                        @if($download->reward === null)
                                                            <span class="badge badge-success badge-soft rounded-full">frei</span>
                                                        @elseif(isset($unlockedDownloadIds[$download->id]))
                                                            <span class="badge badge-info badge-soft rounded-full">freigeschaltet</span>
                                                        @elseif($download->reward->is_active)
                                                            <span class="badge badge-warning badge-soft rounded-full">Belohnung nötig</span>
                                                        @else
                                                            <span class="badge badge-neutral badge-soft rounded-full">pausiert</span>
                                                        @endif
                                                    </div>

                                                    @if($download->description)
                                                        <p class="max-w-3xl text-sm leading-relaxed text-base-content/68 sm:text-base">{{ $download->description }}</p>
                                                    @endif
                                                </div>

                                                <div class="flex shrink-0 items-center">
                                                    @if(isset($unlockedDownloadIds[$download->id]) || $download->reward === null)
                                                        <x-button label="Herunterladen" link="{{ route('downloads.download', $download) }}" icon="o-arrow-down-tray" class="btn-primary btn-sm" />
                                                    @elseif($download->reward->is_active)
                                                        <a href="{{ route('rewards.index') }}" wire:navigate class="inline-flex items-center gap-2 rounded-full border border-base-content/10 bg-base-100 px-4 py-2 text-sm font-medium text-base-content transition hover:border-primary/30 hover:text-primary" title="Unter Belohnungen freischalten">
                                                            <x-icon name="o-lock-closed" class="h-4 w-4" />
                                                            <span>Freischalten</span>
                                                        </a>
                                                    @else
                                                        <span class="inline-flex items-center gap-2 rounded-full border border-base-content/10 bg-base-200/70 px-4 py-2 text-sm font-medium text-base-content/45" title="Die verknüpfte Belohnung ist derzeit nicht verfügbar">
                                                            <x-icon name="o-lock-closed" class="h-4 w-4" />
                                                            <span>Nicht verfügbar</span>
                                                        </span>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </details>
                        @endforeach
                    </div>
                </x-ui.panel>
            @endif

            <div class="space-y-6 xl:sticky xl:top-6">
                <x-ui.panel title="Zugriff und Freischaltung" description="Nicht alle Inhalte stehen sofort bereit. Einige Dateien sind an eine Belohnung gekoppelt, andere direkt im Mitgliederbereich verfügbar.">
                    <ul class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Dateien mit dem Status frei stehen direkt im Mitgliederbereich bereit.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Bei Belohnung nötig gelangst du direkt zum passenden Freischaltbereich.</li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Pausierte Inhalte bleiben sichtbar, bis die verknüpfte Belohnung wieder aktiv ist.</li>
                    </ul>
                </x-ui.panel>

                <x-ui.panel title="Was du hier findest" description="Die Sammlung wächst mit neuen Clubprojekten weiter und bleibt nach Themen sortiert, damit Inhalte schnell wiedergefunden werden.">
                    <div class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <p class="font-medium text-base-content">Bauanleitungen und Vorlagen</p>
                            <p class="mt-1">Material für Clubprojekte, Kreativarbeit und gemeinsame Aktionen.</p>
                        </div>
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <p class="font-medium text-base-content">Texte und Extras</p>
                            <p class="mt-1">Fanstories, ergänzende Unterlagen und weitere exklusive Dateien.</p>
                        </div>
                    </div>
                </x-ui.panel>
            </div>
        </section>
    </x-member-page>
</x-app-layout>
