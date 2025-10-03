<x-app-layout>
    <x-member-page>
        <div class="max-w-5xl mx-auto space-y-6">
            <header class="space-y-2">
                <p class="text-sm font-semibold uppercase tracking-wide text-[#8B0116] dark:text-[#FCA5A5]">
                    Maddraxikon
                </p>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                    Navigation aus dem Maddraxikon
                </h1>
                <p class="text-base text-gray-600 dark:text-gray-300">
                    Auf dieser Seite findest du die aktuelle Navigationsübersicht aus dem Maddraxikon. Die Inhalte werden
                    automatisch aus dem MediaWiki geladen und regelmäßig aktualisiert. Externe Links öffnen im selben
                    Fenster.
                </p>
            </header>

            @if($errorMessage)
                <section
                    class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-rose-900 dark:border-rose-400/40 dark:bg-rose-900/30 dark:text-rose-100"
                    role="alert"
                >
                    <h2 class="text-lg font-semibold">Laden fehlgeschlagen</h2>
                    <p class="mt-2 leading-relaxed">
                        {{ $errorMessage }}
                    </p>
                </section>
            @elseif($content)
                <section
                    class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    aria-labelledby="maddraxikon-navigation-heading"
                >
                    <div class="flex items-center justify-between gap-4">
                        <h2 id="maddraxikon-navigation-heading" class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            Navigationsinhalte
                        </h2>
                        <a
                            href="https://de.maddraxikon.com/index.php?title=Vorlage:Hauptseite/Navigation"
                            class="inline-flex items-center gap-2 rounded-md border border-transparent bg-[#8B0116] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#A61228] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B0116] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
                            target="_blank"
                            rel="noopener"
                        >
                            Original anzeigen
                            <span aria-hidden="true" class="inline-flex h-4 w-4 items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                                    <path d="M10.75 2.75a.75.75 0 0 1 .75-.75h5.75a.75.75 0 0 1 .75.75v5.75a.75.75 0 0 1-1.5 0V4.56l-5.97 5.97a.75.75 0 1 1-1.06-1.06l5.97-5.97h-3.94a.75.75 0 0 1-.75-.75Z" />
                                    <path d="M3.5 4a1.5 1.5 0 0 0-1.5 1.5v10a1.5 1.5 0 0 0 1.5 1.5h10a1.5 1.5 0 0 0 1.5-1.5v-3a.75.75 0 0 0-1.5 0v3a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h3a.75.75 0 0 0 0-1.5h-3Z" />
                                </svg>
                            </span>
                        </a>
                    </div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Alle Links im eingebetteten Inhalt führen direkt zum Maddraxikon.
                    </p>
                    <div
                        class="prose mt-6 max-w-none text-gray-800 dark:prose-invert dark:text-gray-100"
                        role="navigation"
                        aria-label="Navigationsbereich des Maddraxikon"
                    >
                        {!! $content !!}
                    </div>
                </section>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Es sind derzeit keine Inhalte verfügbar.
                </p>
            @endif
        </div>
    </x-member-page>
</x-app-layout>
