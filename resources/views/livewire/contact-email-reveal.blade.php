<div
    x-data="contactEmailReveal({
        sitekey: @js($siteKey),
        captchaEnabled: @js($captchaEnabled),
        token: @entangle('hcaptchaToken').live,
        revealed: @entangle('revealed').live,
        email: @entangle('email').live,
    })"
    x-init="init()"
    x-on:reset-hcaptcha.window="resetCaptcha()"
    x-on:email-revealed.window="focusEmail()"
    class="space-y-4"
>
    <form wire:submit.prevent="reveal" class="space-y-4" aria-describedby="contact-email-hint">
        <p id="contact-email-hint" class="text-sm text-gray-600 dark:text-gray-300">
            {{ __('Zum Schutz vor Spam erfordert das Anzeigen der Kontaktadresse eine hCaptcha-Bestätigung.') }}
        </p>

        <div x-show="captchaEnabled" x-cloak class="space-y-2">
            <div
                x-ref="captcha"
                class="rounded-lg border border-dashed border-gray-300 bg-white p-4 text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                role="group"
                aria-label="{{ __('hCaptcha Verifizierung') }}"
                data-success-message="{{ __('Die Captcha-Prüfung wurde erfolgreich abgeschlossen.') }}"
                data-expired-message="{{ __('Die Captcha-Prüfung ist abgelaufen. Bitte erneut bestätigen.') }}"
            ></div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('Sollte das Captcha nicht geladen werden, lade die Seite neu oder nutze die alternative Kontaktmöglichkeit im Footer.') }}
            </p>
        </div>

        <input type="hidden" x-model="token" aria-hidden="true" />

        @error('hcaptchaToken')
            <p class="text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
        @enderror

        @error('reveal')
            <p class="text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
        @enderror

        <button
            type="submit"
            class="inline-flex items-center gap-2 rounded-lg bg-[#8B0116] px-4 py-2 text-sm font-semibold text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#ff6b7d] disabled:cursor-not-allowed disabled:opacity-50 dark:bg-[#ff4b63] dark:focus-visible:ring-offset-gray-900"
            :disabled="captchaEnabled && ! token"
            :aria-disabled="captchaEnabled && ! token"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M5.25 19.5h13.5a1.5 1.5 0 001.5-1.5V6A1.5 1.5 0 0018.75 4.5H5.25A1.5 1.5 0 003.75 6v12a1.5 1.5 0 001.5 1.5z" />
            </svg>
            <span>{{ __('E-Mail-Adresse anzeigen') }}</span>
        </button>
    </form>

    <div
        x-show="revealed"
        x-transition
        role="status"
        aria-live="polite"
        class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 shadow-sm dark:border-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-100"
    >
        <p class="text-sm font-semibold tracking-wide">{{ __('Kontaktadresse') }}</p>
        <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-100">
            <a
                x-ref="emailLink"
                :href="'mailto:' + email"
                class="underline decoration-dashed underline-offset-4 focus:outline-none focus-visible:rounded focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 focus-visible:ring-offset-emerald-50 dark:focus-visible:ring-offset-emerald-900"
                x-text="email"
            ></a>
        </p>
    </div>
</div>

@once
    @push('scripts')
        <script>
            window.onHCaptchaLoaded = function () {
                window.dispatchEvent(new CustomEvent('hcaptcha-ready'));
            };

            document.addEventListener('alpine:init', () => {
                Alpine.data('contactEmailReveal', (config) => ({
                    sitekey: config.sitekey,
                    captchaEnabled: Boolean(config.captchaEnabled),
                    token: config.token,
                    revealed: config.revealed,
                    email: config.email,
                    widgetId: null,
                    init() {
                        if (this.captchaEnabled) {
                            if (window.hcaptcha) {
                                this.renderCaptcha();
                            } else {
                                window.addEventListener('hcaptcha-ready', () => this.renderCaptcha(), { once: true });
                            }
                        }

                        window.addEventListener('mock-hcaptcha-token', (event) => {
                            if (! this.captchaEnabled) {
                                return;
                            }

                            this.token = event.detail?.token ?? '';
                        });
                    },
                    renderCaptcha() {
                        if (! this.captchaEnabled || this.widgetId !== null || ! window.hcaptcha) {
                            return;
                        }

                        this.widgetId = window.hcaptcha.render(this.$refs.captcha, {
                            sitekey: this.sitekey,
                            callback: (token) => {
                                this.token = token;
                            },
                            'expired-callback': () => {
                                this.token = '';
                            },
                            'error-callback': () => {
                                this.token = '';
                            },
                        });
                    },
                    resetCaptcha() {
                        if (this.widgetId !== null && window.hcaptcha) {
                            window.hcaptcha.reset(this.widgetId);
                        }
                    },
                    focusEmail() {
                        this.$nextTick(() => {
                            if (this.$refs.emailLink) {
                                this.$refs.emailLink.focus({ preventScroll: false });
                            }
                        });
                    },
                }));
            });
        </script>
        <script src="https://js.hcaptcha.com/1/api.js?onload=onHCaptchaLoaded&render=explicit" async defer></script>
    @endpush
@endonce
