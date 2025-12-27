@props([
    'email',
    'variant' => 'desktop', // desktop|mobile
])

@php
    $isMobile = $variant === 'mobile';

    $buttonClass = $isMobile
        ? 'flex-1 flex justify-center items-center bg-gray-600 hover:bg-gray-700 text-white py-2 px-3 rounded'
        : 'inline-flex items-center justify-center bg-gray-600 hover:bg-gray-700 text-white text-xs px-2 py-1 rounded';

    $iconClass = $isMobile ? 'h-4 w-4 mr-1' : 'h-4 w-4';
@endphp

<div x-data="{ emailCopied: false }">
    <button
        type="button"
        data-copy-email
        title="E-Mail kopieren"
        aria-label="E-Mail-Adresse kopieren"
        @click="
            const email = @js($email);
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
        class="{{ $buttonClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
        </svg>

        @if($isMobile)
            <span x-show="!emailCopied">Mail</span>
            <span x-show="emailCopied">Kopiert</span>
        @else
            <span class="ml-1 hidden xl:inline" x-show="!emailCopied">Mail</span>
            <span class="ml-1 hidden xl:inline" x-show="emailCopied">Kopiert</span>
        @endif
    </button>
</div>
