@props(['title', 'id'])
<details id="{{ $id }}" class="omxfc-dashboard-details rounded-2xl border border-base-content/10 bg-base-100 p-4 sm:p-5" x-data="{ expanded: window.innerWidth >= 1024 }" x-bind:open="expanded" x-on:toggle="expanded = $el.open" data-dashboard-details>
    <summary class="min-h-11 cursor-pointer rounded-md py-2 font-display text-xl font-semibold focus-visible:outline-2 focus-visible:outline-primary">
        <h2 class="inline" id="{{ $id }}-title">{{ $title }}</h2>
    </summary>
    <div class="pt-3">{{ $slot }}</div>
</details>
