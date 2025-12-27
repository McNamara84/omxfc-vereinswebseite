<div class="py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @if ($statusMessage)
            <div id="poll-status-message" class="mb-6 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 px-4 py-3 rounded" role="status" aria-live="polite" aria-label="Statusmeldung zur Umfrage">
                {{ $statusMessage }}
            </div>
        @endif

        @if (! $poll)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Umfrage</h1>
                <p class="mt-2 text-gray-600 dark:text-gray-400">Aktuell ist keine Umfrage aktiv.</p>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $poll->question }}</h1>
                <p class="mt-2 text-gray-600 dark:text-gray-400">
                    @if ($poll->visibility->value === 'internal')
                        Diese Umfrage richtet sich an Vereinsmitglieder.
                    @else
                        Diese Umfrage ist öffentlich. Pro IP ist eine Stimme möglich.
                    @endif
                </p>

                @if ($errors->any())
                    <div class="mt-6 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-700 text-red-800 dark:text-red-200 px-4 py-3 rounded" role="alert" aria-live="assertive">
                        <p class="font-semibold">Bitte korrigiere Folgendes:</p>
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form class="mt-6" wire:submit.prevent="submit">
                    <fieldset class="space-y-4" @disabled(! $canVote) @if($statusMessage && ! $canVote) aria-describedby="poll-status-message" @endif>
                        <legend class="sr-only">Antwort auswählen</legend>

                        @foreach ($poll->options as $option)
                            <label class="block rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:bg-gray-50 dark:hover:bg-gray-900 focus-within:ring-2 focus-within:ring-indigo-500">
                                <div class="flex items-start gap-3">
                                    <input
                                        type="radio"
                                        class="mt-1"
                                        name="poll-option"
                                        wire:model="selectedOptionId"
                                        value="{{ $option->id }}"
                                        @disabled(! $canVote)
                                        aria-describedby="option-{{ $option->id }}-desc"
                                    />

                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $option->label }}</div>

                                        <div id="option-{{ $option->id }}-desc" class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            @if ($option->image_url)
                                                <img src="{{ $option->image_url }}" alt="{{ $option->label }}" class="rounded-md border border-gray-200 dark:border-gray-700 w-full max-h-48 object-contain" loading="lazy" decoding="async" />
                                            @endif

                                            @if ($option->link_url)
                                                <div>
                                                    <a href="{{ $option->link_url }}" target="_blank" rel="noopener" class="inline-flex items-center text-indigo-600 dark:text-indigo-400 hover:underline">
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
                        <button
                            type="submit"
                            class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-60"
                            @disabled(! $canVote)
                        >
                            Stimme abgeben
                        </button>

                        @if (! $canVote)
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                @if ($hasVoted)
                                    Abstimmung abgeschlossen.
                                @else
                                    Abstimmung aktuell nicht möglich.
                                @endif
                            </span>
                        @endif
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
