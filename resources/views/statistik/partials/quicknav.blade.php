{{-- resources/views/statistik/partials/quicknav.blade.php --}}
@php($sections = collect($sections ?? []))

@if ($sections->isNotEmpty())
    <nav
        aria-label="Statistikabschnitte"
        data-statistik-nav
        class="bg-base-100 shadow-xl rounded-lg border border-base-content/10 p-4 overflow-x-auto lg:overflow-visible lg:sticky lg:top-24 lg:max-h-[calc(100vh-6rem)]"
    >
        <ul class="flex gap-2 lg:flex-col text-sm text-base-content/70" role="list">
            @foreach ($sections as $section)
                <li class="flex-shrink-0 lg:flex-shrink">
                    <a
                        href="#{{ $section['id'] }}"
                        class="flex flex-col gap-0.5 rounded-md px-3 py-2 transition-colors duration-150 text-base-content/70 hover:bg-base-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary data-[active=true]:bg-primary/10 data-[active=true]:text-primary"
                        data-statistik-nav-link
                        data-section="{{ $section['id'] }}"
                        data-active="false"
                        aria-current="false"
                    >
                        <span class="font-semibold leading-5">{{ $section['label'] }}</span>
                        @if (! empty($section['minPoints']))
                            <span class="text-xs text-base-content/50">ab {{ $section['minPoints'] }} Baxx</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
