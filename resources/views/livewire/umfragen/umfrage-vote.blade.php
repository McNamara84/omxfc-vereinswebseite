<div>
    <x-member-page class="max-w-3xl">
        @if ($statusMessage || ! $canVote)
            <x-alert icon="o-information-circle" class="alert-info mb-6" role="status" aria-live="polite" aria-label="Statusmeldung zur Umfrage">
                {{ $statusMessage ?? 'Abstimmung aktuell nicht möglich.' }}
            </x-alert>
        @endif

        <x-ui.page-header
            :eyebrow="$this->poll ? 'Aktive Abstimmung' : 'Umfrage'"
            :title="$this->poll?->question ?? 'Umfrage'"
            :description="$this->poll
                ? ($this->poll->visibility->value === 'internal'
                    ? 'Diese Umfrage richtet sich an Vereinsmitglieder.'
                    : 'Diese Umfrage ist öffentlich. Pro IP ist eine Stimme möglich.')
                : 'Aktuell ist keine Umfrage aktiv.'"
            data-testid="page-title"
        />

        @if (! $this->poll)
            <x-ui.panel>
                <p class="text-base-content">Aktuell ist keine Umfrage aktiv.</p>
            </x-ui.panel>
        @else
            <x-ui.panel title="Antwort auswählen" description="Wähle genau eine Option aus und gib deine Stimme ab, solange die Abstimmung für dich offen ist.">
                @if ($errors->any())
                    <x-alert icon="o-exclamation-triangle" class="alert-error mt-6" role="alert" aria-live="assertive">
                        <p class="font-semibold">Bitte korrigiere Folgendes:</p>
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif

                <form class="mt-6" wire:submit.prevent="submit">
                    <fieldset class="space-y-4" @disabled(! $canVote) @if(! $canVote) aria-describedby="poll-status-message" @endif>
                        <legend class="sr-only">Antwort auswählen</legend>

                        @foreach ($this->poll->options as $option)
                            <label class="block rounded-lg border border-base-content/10 p-4 hover:bg-base-200 focus-within:ring-2 focus-within:ring-primary cursor-pointer">
                                <div class="flex items-start gap-3">
                                    <input
                                        type="radio"
                                        class="radio radio-primary mt-1"
                                        name="poll-option"
                                        wire:model="selectedOptionId"
                                        value="{{ $option->id }}"
                                        @disabled(! $canVote)
                                        aria-describedby="option-{{ $option->id }}-desc"
                                    />

                                    <div class="flex-1">
                                        <div class="font-semibold">{{ $option->label }}</div>

                                        <div id="option-{{ $option->id }}-desc" class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            @if ($option->image_url)
                                                <img src="{{ $option->image_url }}" alt="{{ $option->label }}" class="rounded-md border border-base-content/10 w-full max-h-48 object-contain" loading="lazy" decoding="async" />
                                            @endif

                                            @if ($option->link_url)
                                                <div>
                                                    <a href="{{ $option->link_url }}" target="_blank" rel="noopener" class="inline-flex items-center text-primary hover:underline">
                                                        Mehr Infos
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </fieldset>

                    <div class="mt-6 flex items-center gap-3">
                        <x-button type="submit" label="Stimme abgeben" icon="o-check" class="btn-primary" :disabled="! $canVote" spinner="submit" />

                        @if (! $canVote)
                            <span class="text-sm text-base-content">
                                @if ($hasVoted)
                                    Abstimmung abgeschlossen.
                                @else
                                    Abstimmung aktuell nicht möglich.
                                @endif
                            </span>
                        @endif
                    </div>
                </form>
            </x-ui.panel>
        @endif
    </x-member-page>
</div>
