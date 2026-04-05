<div>
    @forelse($this->releases as $index => $release)
        <section class="mb-6">
            @php
                $slugVersion = \Illuminate\Support\Str::slug($release['version']);
            @endphp
            <details class="group border border-gray-200 dark:border-gray-700 rounded-lg bg-white/80 dark:bg-gray-900/60 shadow-sm transition"
                     x-data="{ open: {{ $index === 0 ? 'true' : 'false' }} }"
                     x-on:toggle="open = $el.open"
                     @if($index === 0) open @endif>
                <summary
                    id="release-summary-{{ $slugVersion }}"
                    class="flex flex-wrap items-center justify-between gap-4 p-4 cursor-pointer text-lg font-semibold text-gray-900 dark:text-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-900"
                    role="button"
                    :aria-expanded="open ? 'true' : 'false'"
                    aria-controls="release-summary-{{ $slugVersion }}-panel"
                >
                    <div class="flex items-center gap-3">
                        <span class="bg-purple-100 text-purple-800 dark:bg-purple-600 dark:text-white text-base sm:text-lg font-bold rounded px-3 py-1">
                            {{ $release['version'] }}
                        </span>
                        <span class="text-sm sm:text-base text-gray-700 dark:text-gray-300">
                            {{ \Carbon\Carbon::parse($release['pub_date'])->format('d.m.Y') }}
                        </span>
                    </div>
                    <span class="ml-auto text-gray-500 dark:text-gray-400 transition-transform group-open:-rotate-180" aria-hidden="true">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 9 6 6 6-6" />
                        </svg>
                    </span>
                </summary>

                <div id="release-summary-{{ $slugVersion }}-panel"
                     role="region"
                     aria-labelledby="release-summary-{{ $slugVersion }}"
                     class="px-4 pb-4 pt-2 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                    <ul class="list-none space-y-2">
                        @foreach($release['notes'] as $note)
                            <li class="flex items-start gap-2">
                                @if(preg_match('/^\[(\w+)\]\s*(.*)$/', $note, $matches))
                                    @php
                                        $type = strtolower($matches[1]);
                                        $badgeClass = match(true) {
                                            in_array($type, ['new', 'added']) => 'bg-green-600 text-white',
                                            $type === 'fixed' => 'bg-red-600 text-white',
                                            in_array($type, ['improved', 'changed']) => 'bg-blue-600 text-white',
                                            default => 'bg-gray-600 text-white',
                                        };
                                    @endphp
                                    <span class="text-xs font-bold rounded px-2 py-1 min-w-[7rem] text-center {{ $badgeClass }}">
                                        {{ $matches[1] }}
                                    </span>
                                    <span>{{ $matches[2] }}</span>
                                @else
                                    {{ $note }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </details>
        </section>
    @empty
        <p class="text-base-content/80">Keine Changelog-Einträge vorhanden.</p>
    @endforelse
</div>
