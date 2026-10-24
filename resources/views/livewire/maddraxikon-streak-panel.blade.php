<div @if($streak && ($streak['pending'] || $streak['recalculating'])) wire:poll.visible.60s @endif>
    @if($streak)
        <section class="overflow-hidden rounded-2xl border border-primary/25 bg-base-100 shadow-sm" aria-label="Maddraxikon-Serie" data-testid="maddraxikon-streak">
            <div class="h-1 bg-primary" aria-hidden="true"></div>
            <div class="space-y-5 p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-primary">Dein Wissen zählt</p>
                        <h2 class="mt-1 font-display text-xl font-semibold">Maddraxikon-Serie</h2>
                    </div>
                    <span class="badge badge-outline">Täglich mehr Baxx</span>
                </div>
                @if(! $streak['linked'])
                    <p class="text-base leading-relaxed">Jeden aktiven Tag mehr Baxx: <strong>1, 2, 3 … zusätzlich.</strong> Verbinde dein Konto und starte mit einer kleinen gültigen Korrektur oder einem neuen Artikel.</p>
                    <div class="flex gap-3 text-2xl font-semibold text-primary" aria-label="Tag 1: ein Bonus-Baxx, Tag 2: zwei Bonus-Baxx, Tag 3: drei Bonus-Baxx">
                        <span aria-hidden="true">+1</span><span aria-hidden="true">+2</span><span aria-hidden="true">+3</span><span aria-hidden="true">…</span>
                    </div>
                    @if($streak['linking_enabled'])
                        <a class="btn btn-primary min-h-11" href="{{ route('profile.show') }}#maddraxikon-account">Maddraxikon verbinden</a>
                    @else
                        <p class="text-sm">Neue Verknüpfungen sind momentan nicht freigeschaltet.</p>
                    @endif
                @else
                    <div class="grid gap-5 sm:grid-cols-[auto_1fr] sm:items-center">
                        <div aria-live="polite" aria-atomic="true">
                            <p class="font-display text-5xl font-semibold tabular-nums">{{ number_format($streak['current'], 0, ',', '.') }}</p>
                            <p class="mt-1 text-sm text-base-content/80">{{ $streak['current'] === 1 ? 'Tag bestätigt' : 'Tage bestätigt' }}</p>
                            @if($streak['last_confirmed'])<p class="mt-1 text-xs text-base-content/70">Letzte Bestätigung: {{ $streak['last_confirmed'] }}</p>@endif
                        </div>
                        <x-maddraxikon.streak-days :days="$streak['week']" />
                    </div>
                    <p class="text-xs text-base-content/75">✓ bestätigt · ◷ wird geprüft · – keine gültige Aktivität · · noch offen</p>
                    <div class="rounded-xl bg-base-200/70 p-4 text-sm leading-relaxed" role="status">
                        @if($streak['paused'])
                            Die Bonusvergabe ist vorübergehend pausiert. Deine bestätigten Baxx bleiben erhalten.
                        @elseif($streak['delayed'])
                            <strong>Synchronisation verzögert.</strong> Deine Aktivität wird noch abgeglichen. Die angezeigte Serie ist der zuletzt bestätigte Stand.
                        @elseif($streak['recalculating'])
                            <strong>Deine Serie wird neu berechnet.</strong> Die Höhe offener Boni wird noch geprüft.
                        @elseif($streak['today_pending'])
                            <strong>Aktivität erkannt – Bonus wird geprüft.</strong> Die Gutschrift erfolgt frühestens nach {{ $streak['delay_hours'] }} Stunden.
                        @elseif($streak['pending'])
                            Aktivitäten vorheriger Tage werden noch geprüft. Die Höhe des nächsten Bonus steht danach fest.
                        @elseif($streak['current'] > 0)
                            Mit einem Beitrag heute sind <strong>+{{ $streak['next'] }} Bonus-Baxx</strong> möglich.
                        @else
                            Deine nächste Serie startet mit einem gültigen Beitrag und <strong>+1 Bonus-Baxx</strong>.
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ config('maddraxikon.base_url') }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary min-h-11">Im Maddraxikon mitmachen <span class="sr-only">(öffnet einen neuen Tab)</span></a>
                        @if(! $detailed)<a class="btn btn-ghost min-h-11" href="{{ route('profile.show') }}#maddraxikon-serie">Serienverlauf ansehen</a>@endif
                    </div>
                @endif
                <p class="text-xs leading-relaxed text-base-content/75">Ein Bonus je Aktivitätstag, zusätzlich zu regulären Beitrags-Baxx und ohne Bonusobergrenze. Auch kleine gültige Korrekturen zählen. Ein ausgelassener Kalendertag beendet die Serie. Es gilt die Zeit in Berlin.</p>
                @if($streak['last_sync'])<p class="text-xs text-base-content/70">Letzter Import: {{ $streak['last_sync'] }} Uhr</p>@endif
                @if($detailed)
                    <dl class="grid grid-cols-2 gap-3 rounded-xl bg-base-200/70 p-4">
                        <div><dt class="text-xs text-base-content/75">Persönlicher Rekord</dt><dd class="text-xl font-semibold">{{ $streak['record'] }} Tage</dd></div>
                        <div><dt class="text-xs text-base-content/75">Bonusbilanz nach Korrekturen</dt><dd class="text-xl font-semibold">{{ number_format($streak['total'], 0, ',', '.') }} Baxx</dd></div>
                    </dl>
                    <details class="rounded-xl border border-base-content/15 p-4"><summary class="cursor-pointer font-semibold">Die letzten 31 Tage</summary><x-maddraxikon.streak-days class="mt-4" :days="$streak['month']" /></details>
                    <div>
                        <h3 class="font-semibold">Deine Bonusbuchungen</h3>
                        <ul class="mt-3 divide-y divide-base-content/10">
                            @forelse($bookings as $booking)
                                <li class="flex flex-wrap justify-between gap-2 py-3" wire:key="streak-booking-{{ $booking->id }}">
                                    <div><p class="text-sm font-medium">{{ $booking->reason === 'daily_bonus' ? 'Serienbonus' : 'Serienkorrektur' }} für {{ $booking->day->activity_date->format('d.m.Y.') }}</p>
                                        <p class="text-xs text-base-content/75">{{ $booking->reason === 'daily_bonus' ? 'Gültiger Aktivitätstag bestätigt.' : 'Die gültige Tagesfolge wurde neu geprüft.' }} Anspruch: {{ $booking->previous_points }} → {{ $booking->target_points }} Baxx.</p></div>
                                    <span class="font-semibold tabular-nums">{{ $booking->points_delta > 0 ? '+' : '' }}{{ $booking->points_delta }} Baxx</span>
                                </li>
                            @empty<li class="py-3 text-sm text-base-content/75">Sobald dein erster Aktivitätstag bestätigt ist, erscheint hier dein Bonus.</li>@endforelse
                        </ul>
                        {{ $bookings->links() }}
                    </div>
                @endif
            </div>
        </section>
    @endif
</div>
