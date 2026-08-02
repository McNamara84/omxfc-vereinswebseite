<div data-testid="website-feedback-root">
    @if ($sent)
        <div
            x-data="{ visible: true }"
            x-init="setTimeout(() => visible = false, 5000)"
            x-show="visible"
            x-transition.opacity.duration.150ms
            role="status"
            aria-live="polite"
            class="alert alert-success fixed z-50 max-w-sm shadow-xl"
            style="right: max(1rem, env(safe-area-inset-right)); bottom: max(1rem, env(safe-area-inset-bottom));"
            data-testid="website-feedback-success"
        >
            <x-icon name="o-check-circle" class="h-6 w-6 shrink-0" />
            <span>Vielen Dank! Dein Feedback wurde weitergeleitet.</span>
        </div>
    @elseif ($available)
        <button
            type="button"
            x-ref="feedbackTrigger"
            x-on:click="$wire.openFeedback(window.location.href, document.title)"
            x-on:website-feedback-closed.window="$nextTick(() => $refs.feedbackTrigger?.focus())"
            class="btn btn-primary fixed z-40 min-h-11 rounded-full border border-primary-content/15 px-4 shadow-xl shadow-base-content/20 transition duration-150 hover:-translate-y-0.5 hover:shadow-2xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary motion-reduce:transform-none motion-reduce:transition-none"
            style="right: max(1rem, env(safe-area-inset-right)); bottom: max(1rem, env(safe-area-inset-bottom));"
            aria-label="Feedback zur Vereinswebsite geben"
            data-testid="website-feedback-trigger"
        >
            <x-icon name="o-chat-bubble-left-ellipsis" class="h-5 w-5" aria-hidden="true" />
            <span>Feedback</span>
        </button>

        <dialog
            wire:ignore.self
            x-data="{ isOpen: @entangle('showModal').live }"
            x-effect="
                if (isOpen && !$el.open) {
                    $el.showModal();
                    $nextTick(() => $refs.firstFeedbackField?.focus());
                } else if (!isOpen && $el.open) {
                    $el.close();
                }
            "
            x-trap.noscroll="isOpen"
            x-on:cancel.prevent="$wire.closeFeedback()"
            x-on:close="$dispatch('website-feedback-closed')"
            class="modal max-sm:modal-bottom"
            aria-labelledby="website-feedback-title"
            aria-describedby="website-feedback-description"
            data-testid="website-feedback-modal"
        >
            <div class="modal-box w-[calc(100%-1rem)] max-w-xl overflow-y-auto rounded-[1.5rem] max-sm:mb-0 max-sm:max-h-[calc(100dvh-0.5rem)] max-sm:rounded-b-none">
                <button
                    type="button"
                    wire:click="closeFeedback"
                    class="btn btn-circle btn-ghost btn-sm absolute end-3 top-3"
                    aria-label="Feedback-Dialog schließen"
                >
                    <x-icon name="o-x-mark" class="h-5 w-5" aria-hidden="true" />
                </button>

                <header class="mb-5 border-b border-base-content/10 pe-10 pb-4">
                    <h2 id="website-feedback-title" class="text-xl font-semibold">Website-Feedback</h2>
                    <p id="website-feedback-description" class="mt-1 text-sm text-base-content/70">Hilf uns, die Vereinswebsite weiter zu verbessern.</p>
                </header>

                <form wire:submit="submit" class="space-y-5" novalidate>
                    <p class="text-sm leading-relaxed text-base-content/75">
                        Deine Rückmeldung wird per E-Mail an die aktiven Admins und Vorstandsmitglieder gesendet. Die Website speichert keine eigene Kopie des Feedbacks.
                    </p>

                    <fieldset class="space-y-2">
                        <legend class="text-sm font-semibold">Worum geht es? <span class="text-error" aria-hidden="true">*</span></legend>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ($categories as $value => $label)
                                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-base-content/15 bg-base-100 px-3 py-2 text-sm transition hover:border-primary has-checked:border-primary has-checked:bg-primary/10 has-checked:text-primary focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-primary">
                                    <input
                                        type="radio"
                                        wire:model="category"
                                        value="{{ $value }}"
                                        class="radio radio-primary radio-sm"
                                        @if ($loop->first) x-ref="firstFeedbackField" @endif
                                    >
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('category')
                            <p class="text-sm text-error" role="alert">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div x-data="{ count: {{ mb_strlen($message) }} }" class="space-y-1">
                        <label for="website-feedback-message" class="block text-sm font-medium text-base-content">
                            Dein Feedback <span class="text-error" aria-hidden="true">*</span>
                        </label>
                        <textarea
                            id="website-feedback-message"
                            wire:model="message"
                            x-on:input="count = Array.from($event.target.value).length"
                            placeholder="Was funktioniert gut oder was können wir verbessern?"
                            rows="6"
                            minlength="{{ $messageMinLength }}"
                            maxlength="{{ $messageMaxLength }}"
                            class="textarea textarea-bordered w-full"
                            aria-describedby="website-feedback-message-help"
                            aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}"
                            required
                        ></textarea>
                        <div id="website-feedback-message-help">
                            <p class="text-right text-xs text-base-content/60" aria-live="polite">
                                <span x-text="count" data-testid="website-feedback-message-count">{{ mb_strlen($message) }}</span>/{{ $messageMaxLength }} Zeichen
                            </p>
                            @error('message')
                                <p class="text-sm text-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="rounded-xl border border-base-content/10 bg-base-200/65 p-4">
                        <x-checkbox
                            wire:model.live="anonymous"
                            label="Anonym an Admins und Vorstand senden"
                            class="checkbox-primary"
                        />
                        <p class="mt-2 text-xs leading-relaxed text-base-content/70">
                            @if ($anonymous)
                                Dein Name und deine E-Mail-Adresse werden nicht in die Nachricht oder deren Antwortadresse aufgenommen.
                            @else
                                Dein Anzeigename und deine Konto-E-Mail werden mitgesendet, damit Rückfragen möglich sind.
                            @endif
                        </p>
                    </div>

                    @error('delivery')
                        <div class="alert alert-error text-sm" role="alert">
                            <x-icon name="o-exclamation-triangle" class="h-5 w-5 shrink-0" />
                            <span>{{ $message }}</span>
                        </div>
                    @enderror

                    <div class="flex flex-col-reverse gap-2 border-t border-base-content/10 pt-4 sm:flex-row sm:justify-end">
                        <x-button
                            label="Abbrechen"
                            type="button"
                            wire:click="closeFeedback"
                            class="btn-ghost"
                        />
                        <x-button
                            label="Feedback senden"
                            type="submit"
                            icon="o-paper-airplane"
                            class="btn-primary"
                            spinner="submit"
                            wire:loading.attr="disabled"
                            wire:target="submit"
                        />
                    </div>
                </form>
            </div>

            <form method="dialog" class="modal-backdrop">
                <button type="submit" x-on:click.prevent="$wire.closeFeedback()">Feedback-Dialog schließen</button>
            </form>
        </dialog>
    @endif
</div>
