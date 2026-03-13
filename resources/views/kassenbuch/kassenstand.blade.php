<x-app-layout>
    <x-member-page>
        {{-- Header --}}
        <x-header title="Kassenstand" separator data-testid="page-header" />
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Card 1: Mitgliedsbeitrag Status --}}
            <x-card title="Dein Mitgliedsbeitrag" shadow>
                <div class="mb-4">
                    <p class="text-sm text-base-content">Dein aktueller Mitgliedsbeitrag:</p>
                    <p class="text-xl font-semibold">
                        {{ $memberData->mitgliedsbeitrag ? number_format($memberData->mitgliedsbeitrag, 2, ',', '.') . ' €' : 'Nicht festgelegt' }}
                    </p>
                </div>
                
                <div>
                    <p class="text-sm text-base-content">Bezahlt bis:</p>
                    @if($memberData->bezahlt_bis)
                        @php
                            $bezahlt_bis = \Carbon\Carbon::parse($memberData->bezahlt_bis);
                            $heute = \Carbon\Carbon::now();
                            $differenz = $heute->diffInDays($bezahlt_bis, false);
                        @endphp
                        
                        @if($differenz < 0)
                            <p class="mt-1 text-lg font-semibold text-error">
                                Abgelaufen: {{ $bezahlt_bis->format('d.m.Y') }}
                            </p>
                            <x-alert icon="o-exclamation-triangle" class="alert-error mt-3">
                                <strong>Achtung:</strong> Deine Mitgliedschaft ist abgelaufen! Bitte kontaktiere umgehend den Kassenwart, um deine Mitgliedschaft zu verlängern.
                            </x-alert>
                        @elseif($renewalWarning)
                            <p class="mt-1 text-lg font-semibold text-warning">
                                {{ $bezahlt_bis->format('d.m.Y') }}
                            </p>
                            <x-alert icon="o-exclamation-triangle" class="alert-warning mt-3">
                                <strong>Hinweis:</strong> Bitte denke daran rechtzeitig deine Mitgliedschaft zu verlängern, da deine Mitgliedschaft sonst erlischt.
                            </x-alert>
                        @else
                            <p class="mt-1 text-lg font-semibold text-success">
                                {{ $bezahlt_bis->format('d.m.Y') }}
                            </p>
                        @endif
                    @else
                        <p class="mt-1 text-lg font-semibold text-error">
                            Nicht festgelegt
                        </p>
                    @endif
                </div>
            </x-card>
            
            {{-- Card 2: Aktueller Kassenstand --}}
            <x-card title="Aktueller Kassenstand" shadow data-testid="kassenstand-card">
                <p class="text-sm text-base-content">Kassenstand zum {{ \Carbon\Carbon::parse($kassenstand->letzte_aktualisierung)->format('d.m.Y') }}</p>
                <p class="mt-1 text-2xl font-bold {{ $kassenstand->betrag >= 0 ? 'text-success' : 'text-error' }}">
                    {{ number_format($kassenstand->betrag, 2, ',', '.') }} €
                </p>
            </x-card>
        </div>
    </x-member-page>
</x-app-layout>
