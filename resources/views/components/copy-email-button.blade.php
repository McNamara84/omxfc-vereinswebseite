@props([
    'email',
    'variant' => 'desktop', // desktop|mobile
])

@php
    $isMobile = $variant === 'mobile';
@endphp

<div x-data="{ emailCopied: false, email: @js($email) }">
    <span class="sr-only" role="status" aria-live="polite" x-text="emailCopied ? 'E-Mail-Adresse wurde in die Zwischenablage kopiert.' : ''"></span>

    {{-- Default state --}}
    <x-button
        x-show="!emailCopied"
        icon="o-clipboard-document"
        :label="$isMobile ? 'Mail' : null"
        data-copy-email
        title="E-Mail kopieren"
        aria-label="E-Mail-Adresse kopieren"
        @click="
            const markCopied = () => { emailCopied = true; setTimeout(() => emailCopied = false, 2000); };
            const fallback = () => window.prompt('E-Mail kopieren:', email);

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(email)
                    .then(() => markCopied())
                    .catch(err => { console.error('Fehler beim Kopieren: ', err); fallback(); });
            } else {
                console.error('Clipboard API nicht verfügbar oder unsicherer Kontext.');
                fallback();
            }
        "
        class="{{ $isMobile ? 'btn-sm flex-1' : 'btn-xs' }}"
    >
        @unless($isMobile)
            <span class="hidden xl:inline">Mail</span>
        @endunless
    </x-button>

    {{-- Copied state --}}
    <x-button
        x-show="emailCopied"
        x-cloak
        icon="o-check"
        :label="$isMobile ? 'Kopiert' : null"
        class="{{ $isMobile ? 'btn-success btn-sm flex-1' : 'btn-success btn-xs' }}"
    >
        @unless($isMobile)
            <span class="hidden xl:inline">Kopiert</span>
        @endunless
    </x-button>
</div>
