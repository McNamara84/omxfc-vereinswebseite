@props(['title' => __('Confirm Password'), 'content' => __('For your security, please confirm your password to continue.'), 'button' => __('Confirm')])

@php
    $confirmableId = md5($attributes->wire('then'));
@endphp

<span
    {{ $attributes->wire('then') }}
    x-data
    x-ref="span"
    x-on:click="$wire.startConfirmingPassword('{{ $confirmableId }}')"
    x-on:password-confirmed.window="setTimeout(() => $event.detail.id === '{{ $confirmableId }}' && $refs.span.dispatchEvent(new CustomEvent('then', { bubbles: false })), 250);"
>
    {{ $slot }}
</span>

@once
<x-mary-modal wire:model="confirmingPassword" title="{{ $title }}" separator>
    {{ $content }}

    <div class="mt-4" x-data="{}" x-on:confirming-password.window="setTimeout(() => $refs.confirmable_password.focus(), 250)">
        <x-input type="password" class="mt-1 block w-3/4" placeholder="{{ __('Passwort') }}" autocomplete="current-password"
                    x-ref="confirmable_password"
                    wire:model="confirmablePassword"
                    wire:keydown.enter="confirmPassword"
                    error-field="confirmable_password" />
    </div>

    <x-slot:actions>
        <x-button class="btn-ghost" wire:click="stopConfirmingPassword" wire:loading.attr="disabled">
            {{ __('Abbrechen') }}
        </x-button>

        <x-button class="btn-primary ms-3" dusk="confirm-password-button" wire:click="confirmPassword" wire:loading.attr="disabled">
            {{ $button }}
        </x-button>
    </x-slot:actions>
</x-mary-modal>
@endonce
