{{-- resources/views/statistik/partials/quicknav.blade.php --}}
@php($sections = collect($sections ?? []))

@if ($sections->isNotEmpty())
    <div class="relative overflow-hidden rounded-[2rem] border border-base-content/10 bg-base-100/90 p-5 shadow-xl shadow-base-content/5 backdrop-blur lg:sticky lg:top-24">
        <div class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-primary/35 via-accent/25 to-transparent"></div>

        <div class="mb-4 space-y-2">
            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/45">Schnellnavigation</p>
            <h2 class="font-display text-xl font-semibold tracking-tight text-base-content">Statistikabschnitte</h2>
            <p class="text-sm leading-relaxed text-base-content/72">Springe direkt zu freigeschalteten Auswertungen oder prüfe, welche Bereiche noch Baxx kosten.</p>
        </div>

        <nav
            aria-label="Statistikabschnitte"
            data-statistik-nav
            class="overflow-x-auto lg:max-h-[calc(100vh-12rem)] lg:overflow-visible"
        >
            <ul class="flex gap-2 lg:flex-col text-sm text-base-content" role="list">
                @foreach ($sections as $section)
                    <li class="flex-shrink-0 lg:flex-shrink">
                        <a
                            href="#{{ $section['id'] }}"
                            class="flex flex-col gap-1 rounded-[1.1rem] border border-transparent bg-base-100/70 px-3 py-3 transition-colors duration-150 text-base-content hover:border-base-content/10 hover:bg-base-200/75 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary data-[active=true]:border-primary/20 data-[active=true]:bg-primary/10 data-[active=true]:text-primary"
                            data-statistik-nav-link
                            data-section="{{ $section['id'] }}"
                            data-active="false"
                            aria-current="false"
                        >
                            <span class="font-semibold leading-5">{{ $section['label'] }}</span>
                            @php($reward = ($statistikRewards ?? collect())->get('statistik-' . $section['id']))
                            @php($isUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs ?? [], true))
                            @if ($isUnlocked)
                                <span class="text-xs text-success">Freigeschaltet</span>
                            @elseif ($reward && $reward->is_active)
                                <span class="text-xs text-base-content">{{ $reward->cost_baxx }} Baxx</span>
                            @elseif ($reward && ! $reward->is_active)
                                <span class="text-xs text-base-content/50">Derzeit nicht verfügbar</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
@endif
