@auth
    <div
        id="tour-runner-root"
        data-tour-current-url="{{ route('touren.current') }}"
        data-tour-start-url-template="{{ route('touren.start', '__TOUR_ASSIGNMENT__') }}"
        data-tour-progress-url-template="{{ route('touren.progress', '__TOUR_ASSIGNMENT__') }}"
        data-tour-dismiss-url-template="{{ route('touren.dismiss', '__TOUR_ASSIGNMENT__') }}"
        data-tour-complete-url-template="{{ route('touren.complete', '__TOUR_ASSIGNMENT__') }}"
        class="pointer-events-none"
        aria-live="polite"
    >
        <div id="tour-runner-backdrop" class="tour-runner-backdrop hidden"></div>
        <div id="tour-runner-highlight" class="tour-runner-highlight hidden"></div>

        <section id="tour-runner-panel" class="tour-runner-panel hidden" aria-label="Geführte Tour">
            <div class="card border border-base-content/10 bg-base-100/96 shadow-2xl backdrop-blur">
                <div class="card-body gap-4">
                    <div class="flex items-center justify-between gap-3">
                        <x-badge value="Geführte Tour" class="badge-primary badge-sm" />
                        <p id="tour-runner-counter" class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45"></p>
                    </div>

                    <div class="space-y-2">
                        <h2 id="tour-runner-title" class="font-display text-xl font-semibold text-base-content"></h2>
                        <p id="tour-runner-description" class="text-sm leading-6 text-base-content/72"></p>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/45">
                            <span>Fortschritt</span>
                            <span id="tour-runner-progress-label"></span>
                        </div>
                        <div class="h-2 rounded-full bg-base-200/90">
                            <div id="tour-runner-progress-bar" class="h-full w-0 rounded-full bg-primary transition-[width] duration-200"></div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap gap-2">
                            <button id="tour-runner-skip" type="button" class="btn btn-ghost btn-sm">Später</button>
                            <button id="tour-runner-back" type="button" class="btn btn-ghost btn-sm">Zurück</button>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button id="tour-runner-next" type="button" class="btn btn-primary btn-sm">
                                <span>Weiter</span>
                                <x-icon name="o-arrow-right" class="h-4 w-4" />
                            </button>
                            <button id="tour-runner-complete" type="button" class="btn btn-success btn-sm hidden">
                                <span>Tour abschließen</span>
                                <x-icon name="o-check" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endauth