<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn btn-ghost']) }}>
    {{ $slot }}
</button>
