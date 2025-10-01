{{-- resources/views/statistik/partials/quicknav.blade.php --}}
@php($sections = collect($sections ?? []))

@if ($sections->isNotEmpty())
    <nav
        aria-label="Statistikabschnitte"
        data-statistik-nav
        class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg border border-gray-200 dark:border-gray-700 p-4 overflow-x-auto lg:overflow-visible lg:sticky lg:top-24 lg:max-h-[calc(100vh-6rem)]"
    >
        <ul class="flex gap-2 lg:flex-col text-sm text-gray-700 dark:text-gray-300" role="list">
            @foreach ($sections as $section)
                <li class="flex-shrink-0 lg:flex-shrink">
                    <a
                        href="#{{ $section['id'] }}"
                        class="flex flex-col gap-0.5 rounded-md px-3 py-2 transition-colors duration-150 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/70 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8B0116] dark:focus-visible:outline-[#FF6B81] data-[active=true]:bg-[#8B0116]/10 data-[active=true]:text-[#8B0116] data-[active=true]:dark:bg-[#FF6B81]/15 data-[active=true]:dark:text-[#FF6B81]"
                        data-statistik-nav-link
                        data-section="{{ $section['id'] }}"
                        data-active="false"
                        aria-current="false"
                    >
                        <span class="font-semibold leading-5">{{ $section['label'] }}</span>
                        @if (! empty($section['minPoints']))
                            <span class="text-xs text-gray-500 dark:text-gray-400">ab {{ $section['minPoints'] }} Baxx</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
