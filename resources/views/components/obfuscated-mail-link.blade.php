@props([
    'email',
    'label' => 'Kontakt aufnehmen',
    'class' => 'link link-primary',
    'ariaLabel' => 'Kontakt per E-Mail aufnehmen',
])

@php
    $encodedEmail = base64_encode($email);
@endphp

<span x-data="{ encodedEmail: @js($encodedEmail), href: null }" x-init="href = `mailto:${atob(encodedEmail)}`" class="inline-flex items-center">
    <a x-cloak x-bind:href="href" class="{{ $class }}" aria-label="{{ $ariaLabel }}">{{ $label }}</a>

    <noscript>
        <span class="text-base-content/70">über das Mitgliedernetzwerk</span>
    </noscript>
</span>