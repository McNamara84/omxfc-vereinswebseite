<div>
    @forelse($this->releases as $index => $release)
        <section class="mb-4">
            @php
                $slugVersion = \Illuminate\Support\Str::slug($release['version']);
            @endphp
            <details class="group overflow-hidden rounded-[1.5rem] border border-base-content/10 bg-base-100/88 shadow-md shadow-base-content/5 transition"
                     x-data="{ open: {{ $index === 0 ? 'true' : 'false' }} }"
                     x-on:toggle="open = $el.open"
                     @if($index === 0) open @endif>
                <summary
                    id="release-summary-{{ $slugVersion }}"
                    class="flex cursor-pointer flex-wrap items-center justify-between gap-4 p-4 text-lg font-semibold text-base-content focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-base-100 sm:p-5"
                    role="button"
                    :aria-expanded="open ? 'true' : 'false'"
                    aria-controls="release-summary-{{ $slugVersion }}-panel"
                >
                    <div class="flex items-center gap-3">
                        <span class="badge badge-primary badge-outline rounded-full px-3 py-3 text-sm font-semibold sm:text-base">
                            {{ $release['version'] }}
                        </span>
                        <span class="text-sm font-medium text-base-content/56 sm:text-base">
                            {{ \Carbon\Carbon::parse($release['pub_date'])->format('d.m.Y') }}
                        </span>
                    </div>
                    <span class="ml-auto inline-flex h-10 w-10 items-center justify-center rounded-full bg-base-200/70 text-base-content/58 transition-transform group-open:-rotate-180" aria-hidden="true">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 9 6 6 6-6" />
                        </svg>
                    </span>
                </summary>

                <div id="release-summary-{{ $slugVersion }}-panel"
                     role="region"
                     aria-labelledby="release-summary-{{ $slugVersion }}"
                     class="border-t border-base-content/10 bg-base-100/72 px-4 pb-4 pt-3 sm:px-5 sm:pb-5">
                    <ul class="list-none space-y-3">
                        @foreach($release['notes'] as $note)
                            <li class="flex items-start gap-3 rounded-[1rem] border border-base-content/10 bg-base-100/70 px-3 py-3">
                                @if(preg_match('/^\[(\w+)\]\s*(.*)$/', $note, $matches))
                                    @php
                                        $type = strtolower($matches[1]);
                                        $badgeClass = match(true) {
                                            in_array($type, ['new', 'added']) => 'badge-success badge-soft',
                                            $type === 'fixed' => 'badge-error badge-soft',
                                            in_array($type, ['improved', 'changed']) => 'badge-info badge-soft',
                                            default => 'badge-neutral badge-soft',
                                        };
                                    @endphp
                                    <span class="badge min-w-28 justify-center rounded-full px-3 py-3 text-center text-xs font-bold {{ $badgeClass }}">
                                        {{ $matches[1] }}
                                    </span>
                                    <span class="flex-1 text-sm leading-relaxed text-base-content/76 sm:text-base">{{ $matches[2] }}</span>
                                @else
                                    <span class="text-sm leading-relaxed text-base-content/76 sm:text-base">{{ $note }}</span>
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
